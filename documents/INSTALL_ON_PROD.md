# Installing on Spiderman (Production)

Deploy `pl-opensocial` on Spiderman (Linode x86) behind the existing nginx server at **opensocial.performantlabs.com**.

---

## Architecture

```
Client → nginx (SSL + static files) → fastcgi_pass 127.0.0.1:9000 → PHP-FPM container
```

- **nginx** runs on the host, serves static assets (CSS, JS, images, theme files) directly and forwards PHP requests to the container
- **PHP-FPM** runs inside the container, bind-mounts the project directory so both nginx and PHP-FPM see the same files
- **MariaDB** runs in a separate container with a persistent volume

---

## Prerequisites

Spiderman must have:

- Docker Engine 24.0+
- Docker Compose v2
- Git
- PHP 8.3 + Composer 2 (for running `composer install` on the host)
- nginx (already running)
- Certbot (for SSL)

---

## Step 1 — Clone the repository

```bash
git clone <repo-url> /opt/pl-opensocial
cd /opt/pl-opensocial
```

## Step 2 — Install Composer dependencies

```bash
composer install --no-dev --optimize-autoloader --prefer-dist
```

This downloads Drupal core, Open Social, contributed modules/themes, and JS libraries into the project directory. Host nginx will serve static files directly from here.

> This step requires PHP 8.3 and Composer 2 on the host. Install with:
> ```bash
> sudo apt install php8.3-cli php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd unzip
> curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
> ```

## Step 3 — Create the environment file

```bash
cp .env.example .env
nano .env
```

Generate a hash salt:

```bash
openssl rand -base64 64 | tr -d '\n'; echo
```

The `.env` file should look like:

```env
MYSQL_ROOT_PASSWORD=<strong-root-password>
MYSQL_DATABASE=opensocial
MYSQL_USER=opensocial
MYSQL_PASSWORD=<strong-db-password>
DRUPAL_HASH_SALT=<paste-generated-hash-salt>
DRUPAL_ADMIN_USER=admin
DRUPAL_ADMIN_PASS=<strong-admin-password>
DRUPAL_TRUSTED_HOST=opensocial\.performantlabs\.com
```

## Step 4 — Create the private files directory

```bash
mkdir -p private
```

## Step 5 — Build and start the containers

```bash
docker compose up -d --build
```

This will:

1. Build the PHP-FPM image (~2 min on x86)
2. Start MariaDB 11.8 and wait for it to be healthy
3. Run `drush site:install` with the Open Social profile
4. Run post-install configuration (UUID fixup, entity cleanup, config import)
5. Start PHP-FPM listening on `127.0.0.1:9000`

Monitor progress:

```bash
docker compose logs -f web
```

Wait until you see:

```
==> Starting PHP-FPM ...
NOTICE: ready to handle connections
```

## Step 6 — Configure nginx

Create the site config:

```bash
sudo nano /etc/nginx/sites-available/opensocial
```

Paste the following:

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

    root /opt/pl-opensocial/web;
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

    # PHP → container PHP-FPM
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

Enable the site and test:

```bash
sudo ln -s /etc/nginx/sites-available/opensocial /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Step 7 — Set up SSL

```bash
sudo certbot --nginx -d opensocial.performantlabs.com
```

> If the certificate hasn't been obtained yet, comment out the `ssl_certificate` lines first, reload nginx, run Certbot, then let Certbot add them back.

## Step 8 — Verify

```bash
curl -s -o /dev/null -w "%{http_code}" https://opensocial.performantlabs.com
```

Should return `200` or `302`. Open the site in a browser and log in with the admin credentials from your `.env` file.

---

## Day-to-day operations

### After code changes

```bash
cd /opt/pl-opensocial
git pull
composer install --no-dev --optimize-autoloader --prefer-dist
docker compose up -d --build
```

### Drush commands

```bash
docker compose exec web drush cr                  # cache rebuild
docker compose exec web drush config:export -y    # export config
docker compose exec web drush config:import -y    # import config
docker compose exec web drush uli                 # one-time login link
```

### Database backup

```bash
docker compose exec db mysqldump -uopensocial -p opensocial > backup-$(date +%Y%m%d).sql
```

### Database restore

```bash
docker compose exec -T db mysql -uopensocial -p opensocial < backup.sql
```

### View logs

```bash
docker compose logs -f web    # PHP-FPM
docker compose logs -f db     # MariaDB
```

### Restart containers

```bash
docker compose restart
```

### Full reset (wipes database and files)

```bash
docker compose down -v
docker compose up -d --build
```
