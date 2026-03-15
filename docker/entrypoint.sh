#!/bin/bash
set -euo pipefail

echo "============================================"
echo "  pl-opensocial Docker entrypoint"
echo "============================================"

# ---- Place Docker settings into the bind-mounted web dir ----
if [ -f /usr/local/share/settings.docker.php ]; then
  cp /usr/local/share/settings.docker.php /var/www/html/web/sites/default/settings.docker.php
  chown www-data:www-data /var/www/html/web/sites/default/settings.docker.php
fi

# ---- Ensure runtime directories ----
mkdir -p /var/www/html/private /var/www/html/web/sites/default/files
chown -R www-data:www-data \
  /var/www/html/web/sites/default/files \
  /var/www/html/private

# ---- Wait for MariaDB ----
echo "==> Waiting for MariaDB at ${MYSQL_HOST:-db}:3306 ..."
MAX_TRIES=60
COUNT=0
until mysqladmin ping -h "${MYSQL_HOST:-db}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" --silent 2>/dev/null; do
  COUNT=$((COUNT + 1))
  if [ "$COUNT" -ge "$MAX_TRIES" ]; then
    echo "ERROR: MariaDB not reachable after ${MAX_TRIES} attempts. Aborting."
    exit 1
  fi
  sleep 2
done
echo "==> MariaDB is ready."

# ---- Check if Drupal is already installed ----
INSTALLED=false
if drush status --field=bootstrap 2>/dev/null | grep -q "Successful"; then
  INSTALLED=true
fi

if [ "$INSTALLED" = false ]; then
  echo "==> Site not installed. Running site:install ..."

  drush site:install social \
    --account-name="${DRUPAL_ADMIN_USER:-admin}" \
    --account-pass="${DRUPAL_ADMIN_PASS:-admin}" \
    --site-name="Open Social" \
    --site-mail=admin@example.com \
    --locale=en \
    --db-url="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST:-db}:3306/${MYSQL_DATABASE}" \
    -y

  echo "==> Running post-install configuration ..."

  # Set site UUID to match config/sync
  SYNC_UUID=$(grep "^uuid:" /var/www/html/config/sync/system.site.yml | awk '{print $2}')
  drush config:set system.site uuid "$SYNC_UUID" -y

  # Truncate seed entities that conflict with config:import
  echo "==> Deleting seed entities ..."
  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE taxonomy_term_data;
  TRUNCATE TABLE taxonomy_term_field_data;
  TRUNCATE TABLE taxonomy_term_field_revision;
  TRUNCATE TABLE taxonomy_term_revision;
  TRUNCATE TABLE taxonomy_term__parent;
  TRUNCATE TABLE taxonomy_term__field_group_type_icon;
  TRUNCATE TABLE taxonomy_term__field_topic_type_icon;
  TRUNCATE TABLE taxonomy_term_revision__parent;
  TRUNCATE TABLE taxonomy_term_revision__field_group_type_icon;
  TRUNCATE TABLE taxonomy_term_revision__field_topic_type_icon;
  SET FOREIGN_KEY_CHECKS=1;
  "

  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE menu_link_content;
  TRUNCATE TABLE menu_link_content_data;
  TRUNCATE TABLE menu_link_content_field_revision;
  TRUNCATE TABLE menu_link_content_revision;
  SET FOREIGN_KEY_CHECKS=1;
  "

  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE crop;
  TRUNCATE TABLE crop_field_data;
  TRUNCATE TABLE crop_field_revision;
  TRUNCATE TABLE crop_revision;
  SET FOREIGN_KEY_CHECKS=1;
  "

  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE block_content;
  TRUNCATE TABLE block_content_field_data;
  TRUNCATE TABLE block_content_field_revision;
  TRUNCATE TABLE block_content_revision;
  SET FOREIGN_KEY_CHECKS=1;
  " 2>/dev/null || true

  for tbl in $(drush sql:query "SHOW TABLES LIKE 'block_content%'" 2>/dev/null); do
    drush sql:query "TRUNCATE TABLE \`$tbl\`;" 2>/dev/null || true
  done

  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE profile;
  TRUNCATE TABLE profile_revision;
  SET FOREIGN_KEY_CHECKS=1;
  " 2>/dev/null || true

  for tbl in $(drush sql:query "SHOW TABLES LIKE 'profile%'" 2>/dev/null); do
    drush sql:query "TRUNCATE TABLE \`$tbl\`;" 2>/dev/null || true
  done

  drush sql:query "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE search_api_item;
  TRUNCATE TABLE search_api_task;
  SET FOREIGN_KEY_CHECKS=1;
  "

  # Fix UUID mismatches for config entities
  echo "==> Fixing config entity UUIDs ..."
  drush php:eval "
  \$configs = [
    'block_content.type.basic',
    'block_content.type.hero_call_to_action_block',
    'block_content.type.platform_intro',
    'language.entity.en',
  ];
  foreach (\$configs as \$name) {
    \$file = '/var/www/html/config/sync/' . \$name . '.yml';
    if (!file_exists(\$file)) continue;
    \$data = \Drupal\Core\Serialization\Yaml::decode(file_get_contents(\$file));
    \$active = \Drupal::configFactory()->getEditable(\$name);
    if (!\$active->isNew() && \$active->get('uuid') !== \$data['uuid']) {
      \$active->set('uuid', \$data['uuid'])->save();
      echo 'Fixed UUID: ' . \$name . PHP_EOL;
    }
  }
  "

  # Import config with retry loop
  echo "==> Importing configuration ..."
  MAX_PASSES=8
  for i in $(seq 1 $MAX_PASSES); do
    echo "--- Pass $i ---"

    # Pre-create missing field storages
    drush php:eval "
    \$missing = ['field.storage.activity.field_activity_entity', 'field.storage.group_content.field_grequest_message'];
    foreach (\$missing as \$name) {
      \$file = '/var/www/html/config/sync/' . \$name . '.yml';
      if (!file_exists(\$file)) { echo 'Skipping (not in sync): ' . \$name . PHP_EOL; continue; }
      if (!\Drupal::config(\$name)->isNew()) { echo 'Already present: ' . \$name . PHP_EOL; continue; }
      \$data = \Drupal\Core\Serialization\Yaml::decode(file_get_contents(\$file));
      unset(\$data['uuid'], \$data['_core']);
      \Drupal\field\Entity\FieldStorageConfig::create(\$data)->save();
      echo 'Pre-created: ' . \$name . PHP_EOL;
    }
    " 2>&1 | grep -v '^\[warning\]'

    drush cache:rebuild > /dev/null 2>&1

    if drush config:import -y 2>&1; then
      echo "Config import succeeded on pass $i."
      break
    fi

    if [ "$i" -eq "$MAX_PASSES" ]; then
      echo "WARNING: config:import still failing after $MAX_PASSES passes."
    fi
  done

  drush cache:rebuild
  echo "==> Site installation complete."

else
  echo "==> Site already installed. Running updates ..."
  drush updatedb -y || true
  drush cache:rebuild
  echo "==> Updates complete."
fi

echo "==> Starting PHP-FPM ..."
exec php-fpm --nodaemonize
