#!/bin/bash
set -e

echo "==> Waiting for MySQL..."
until mysqladmin ping -h "${DB_HOST:-mysql}" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" --silent 2>/dev/null; do
    echo "    MySQL not ready, retrying in 2s..."
    sleep 2
done
echo "==> MySQL is ready."

echo "==> Waiting for Redis..."
until redis-cli -h "${REDIS_HOST:-redis}" ping 2>/dev/null | grep -q PONG; do
    echo "    Redis not ready, retrying in 2s..."
    sleep 2
done
echo "==> Redis is ready."

echo "==> Generating config.php..."
php /usr/local/bin/generate-config.php

if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "==> Running composer install..."
    composer install --no-dev --no-interaction --optimize-autoloader --working-dir=/var/www/html
fi

echo "==> Checking database..."
DB_HOST="${DB_HOST:-mysql}"
TABLE_EXISTS=$(mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${MYSQL_DATABASE}' AND table_name='gameServers'" 2>/dev/null || echo "0")

if [ "$TABLE_EXISTS" = "0" ] && [ -f /var/www/html/maindb.sql ]; then
    echo "==> Importing maindb.sql..."
    mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" < /var/www/html/maindb.sql
    echo "==> Database imported."
fi

echo "==> Ensuring admin_users table and default admin..."
ADMIN_PASS="${ADMIN_PASSWORD:-admin123}"
ADMIN_HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' -- "$ADMIN_PASS")
mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -e "
  CREATE TABLE IF NOT EXISTS admin_users (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(100) NOT NULL,
    password varchar(255) NOT NULL,
    role enum('super','admin') NOT NULL DEFAULT 'admin',
    created_at int(10) UNSIGNED NOT NULL,
    last_login int(10) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  INSERT IGNORE INTO admin_users (username, password, role, created_at)
  VALUES ('admin', '$ADMIN_HASH', 'super', UNIX_TIMESTAMP());
" 2>/dev/null || echo "    (admin_users setup skipped — table may already exist)"

echo "==> Creating writable directories..."
mkdir -p /var/www/html/servers
chown -R www-data:www-data /var/www/html/servers

echo "==> Starting cron..."
cron

echo "==> Starting php-fpm..."
exec "$@"
