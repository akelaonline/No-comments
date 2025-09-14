#!/bin/sh
set -e

WP_PATH="/var/www/html"

# Resolve DB settings from environment (supports WORDPRESS_* and DB_* variables)
DB_NAME="${WORDPRESS_DB_NAME:-${DB_NAME:-wordpress}}"
DB_USER="${WORDPRESS_DB_USER:-${DB_USER:-wp}}"
DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-${DB_PASSWORD:-wp}}"
DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"

echo "[wp-setup] Ensuring WordPress core files exist..."
while [ ! -f "$WP_PATH/wp-admin/install.php" ]; do
  sleep 2
done

echo "[wp-setup] Ensuring wp-config.php exists..."
if [ ! -f "$WP_PATH/wp-config.php" ]; then
  echo "[wp-setup] Creating wp-config.php ..."
  wp --path="$WP_PATH" config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST" --skip-check --allow-root
fi

echo "[wp-setup] Waiting for DB to be ready..."
until wp --path="$WP_PATH" db check --allow-root >/dev/null 2>&1; do
  sleep 2
done

if wp --path="$WP_PATH" core is-installed --allow-root >/dev/null 2>&1; then
  echo "[wp-setup] WordPress already installed."
else
  URL="${WP_SITEURL:-http://localhost:${WP_PORT:-8080}}"
  TITLE="${WP_TITLE:-NO Comments Dev}"
  ADMIN_USER="${WP_ADMIN_USER:-admin}"
  ADMIN_PASS="${WP_ADMIN_PASSWORD:-admin}"
  ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
  echo "[wp-setup] Installing WordPress at $URL ..."
  wp --path="$WP_PATH" core install --url="$URL" --title="$TITLE" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email --allow-root
fi

echo "[wp-setup] Activating plugin no-comments..."
if wp --path="$WP_PATH" plugin is-installed no-comments --allow-root >/dev/null 2>&1; then
  wp --path="$WP_PATH" plugin activate no-comments --allow-root || true
  wp --path="$WP_PATH" option update no_comments_enabled 1 --allow-root || true
else
  echo "[wp-setup] Plugin no-comments not found in plugins dir."
fi

echo "[wp-setup] Setting permalink structure..."
wp --path="$WP_PATH" rewrite structure '/%postname%/' --hard --allow-root || true
wp --path="$WP_PATH" rewrite flush --hard --allow-root || true

echo "[wp-setup] Done."
