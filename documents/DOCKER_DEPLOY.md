# Docker Deployment Guide — Spiderman (Linode)

Deploy `pl-opensocial` as a Docker container (PHP-FPM) behind the host's existing nginx.

---

## Architecture

```
Client → Host nginx (SSL) → fastcgi_pass 127.0.0.1:9000 → PHP-FPM container
                ↓
         Static files served directly from named volume
```

The container runs **PHP-FPM only**. The host's nginx serves static files and forwards PHP requests via FastCGI.

---

## Prerequisites

| Tool | Notes |
|---|---|
| Docker Engine | 24.0+ |
| Docker Compose v2 | Bundled with modern Docker Engine |
| Git | To clone the repository |
| Host nginx | Already on Spiderman, handles SSL + static files + FastCGI |

---

## 1. Clone the repository

```bash
git clone <repo-url> /opt/pl-opensocial
cd /opt/pl-opensocial
```

## 2. Create the environment file

```bash
cp .env.example .env
nano .env
```

Fill in **all** values. Generate a hash salt with:

```bash
openssl rand -base64 64 | tr -d '\n'
```

## 3. Build and start

```bash
docker compose up -d --build
```

First run will:
1. Build the PHP-FPM image and run `composer install`
2. Start MariaDB and wait for it to be healthy
3. Run `drush site:install` with Open Social profile
4. Run post-install config import (UUID fixups, entity cleanup, retry loop)
5. Start PHP-FPM listening on port 9000

> **First build takes 10–15 minutes** (Composer downloads + Drupal install). Subsequent rebuilds after code changes are faster due to Docker layer caching.

## 4. Locate the web root volume

The `web_root` Docker volume contains the full Drupal web root (core, contrib, libraries, etc.). Find its path on the host:

```bash
docker volume inspect pl-opensocial_web_root --format '{{ .Mountpoint }}'
```

You'll need this path for the nginx config below (referred to as `WEB_ROOT_PATH`).

## 5. Configure host nginx

Add a server block (e.g. `/etc/nginx/sites-available/opensocial`):

```nginx
server {
    listen 80;
    server_name opensocial.performantlabs.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name opensocial.performantlabs.com;

    ssl_certificate     /etc/letsencrypt/live/opensocial.performantlabs.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/opensocial.performantlabs.com/privkey.pem;

    # Point to the Docker volume mount path
    root WEB_ROOT_PATH;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location @rewrite {
        rewrite ^ /index.php;
    }

    # Drupal image styles
    location ~ ^/sites/.*/files/styles/ {
        try_files $uri @rewrite;
    }

    # PHP → container's PHP-FPM
    location ~ '\.php$|^/update.php' {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_intercept_errors off;
        fastcgi_read_timeout 600;
        fastcgi_param SERVER_NAME $host;
        fastcgi_param HTTPS on;
    }

    # Deny hidden files (except .well-known)
    location ~* /\.(?!well-known\/) {
        deny all;
    }

    # Deny backup/config/source files
    location ~* (?:\.(?:bak|conf|dist|fla|in[ci]|log|psd|sh|sql|sw[op])|~)$ {
        deny all;
    }

    # Private file serving via Drupal
    location ^~ /system/files/ {
        log_not_found off;
        access_log off;
        expires 30d;
        try_files $uri @rewrite;
    }

    # Static assets
    location ~* \.(jpg|jpeg|gif|png|ico|cur|gz|svg|svgz|mp4|ogg|ogv|webm|webp|htc)$ {
        try_files $uri @rewrite;
        expires max;
        log_not_found off;
    }

    # JS & CSS
    location ~* \.(js|css)$ {
        try_files $uri @rewrite;
        expires 7d;
        log_not_found off;
    }
}
```

Replace `WEB_ROOT_PATH` with the actual Docker volume path from step 4.

Then:

```bash
sudo ln -s /etc/nginx/sites-available/opensocial /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

For the SSL certificate:

```bash
sudo certbot --nginx -d opensocial.performantlabs.com
```

## 6. Verify

```bash
# Check containers are running
docker compose ps

# Check logs
docker compose logs -f web

# Test that PHP-FPM is responding
curl -s -o /dev/null -w "%{http_code}" https://opensocial.performantlabs.com
```

---

## After code changes

```bash
cd /opt/pl-opensocial
git pull
docker compose up -d --build
```

The entrypoint detects the site is already installed and runs `drush updatedb` + `drush cache:rebuild` instead of a fresh install.

## Database operations

```bash
# Backup
docker compose exec db mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" > backup-$(date +%Y%m%d).sql

# Restore
docker compose exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < backup.sql

# Drush commands
docker compose exec web drush cr
docker compose exec web drush config:export -y
docker compose exec web drush config:import -y
```

## Completely reset

```bash
docker compose down -v    # -v removes volumes (DB data + files)
docker compose up -d --build
```

## Logs

```bash
docker compose logs -f web    # PHP-FPM logs
docker compose logs -f db     # MariaDB logs
```
