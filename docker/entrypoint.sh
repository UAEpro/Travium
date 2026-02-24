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

echo "==> Running database migrations..."
# Create migration tracking table if it doesn't exist
mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -e "
  CREATE TABLE IF NOT EXISTS schema_migrations (
    id int(11) NOT NULL AUTO_INCREMENT,
    filename varchar(255) NOT NULL,
    applied_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY filename (filename)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
" 2>/dev/null

# Run any pending migrations from /var/www/html/migrations/ in order
MIGRATIONS_DIR="/var/www/html/migrations"
if [ -d "$MIGRATIONS_DIR" ]; then
    for migration in $(ls "$MIGRATIONS_DIR"/*.sql 2>/dev/null | sort); do
        MIGRATION_FILE=$(basename "$migration")
        ALREADY_APPLIED=$(mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -sse \
            "SELECT COUNT(*) FROM schema_migrations WHERE filename='$MIGRATION_FILE'" 2>/dev/null || echo "0")

        if [ "$ALREADY_APPLIED" = "0" ]; then
            echo "    Applying migration: $MIGRATION_FILE"
            MIGRATION_OUTPUT=$(mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" < "$migration" 2>&1)
            MIGRATION_EXIT=$?
            if [ $MIGRATION_EXIT -eq 0 ]; then
                mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -e \
                    "INSERT INTO schema_migrations (filename) VALUES ('$MIGRATION_FILE')" 2>/dev/null
                echo "    Applied: $MIGRATION_FILE"
            else
                # Check if the error is because the change already exists (e.g., fresh install from maindb.sql)
                if echo "$MIGRATION_OUTPUT" | grep -qi "duplicate\|already exists"; then
                    mysql -h "$DB_HOST" -u "${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" -e \
                        "INSERT IGNORE INTO schema_migrations (filename) VALUES ('$MIGRATION_FILE')" 2>/dev/null
                    echo "    Skipped (already applied): $MIGRATION_FILE"
                else
                    echo "    ERROR applying migration $MIGRATION_FILE: $MIGRATION_OUTPUT"
                fi
            fi
        fi
    done
    echo "==> Migrations complete."
else
    echo "    No migrations directory found, skipping."
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
