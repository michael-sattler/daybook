#!/bin/bash
set -e

# /run is tmpfs and won't survive a container restart, so this directory has
# to be recreated every boot, not just on first run.
mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld

# Note: apt's mariadb-server postinst already runs mariadb-install-db at image
# build time, so /var/lib/mysql is never "empty" even on a brand new named
# volume (Docker auto-populates it from the image layer). So instead of
# checking "is this the first run", everything below is idempotent and just
# runs on every boot: create db/user/grants if missing, import schema.sql
# only if the target database has no tables yet.
mysqld_safe --skip-networking --datadir=/var/lib/mysql &
until mysqladmin ping --silent 2>/dev/null; do sleep 1; done

mysql -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\`;"
mysql -e "CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';"
mysql -e "CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';"
mysql -e "GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

TABLE_COUNT=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${MYSQL_DATABASE}';")
if [ "$TABLE_COUNT" -eq 0 ] && [ -f /var/www/html/sql/schema.sql ]; then
  echo "[entrypoint] Empty database - importing sql/schema.sql..."
  mysql "${MYSQL_DATABASE}" < /var/www/html/sql/schema.sql
fi

mysqladmin shutdown

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
