#!/bin/bash
set -e

cd /var/www/magento2

echo "Waiting for database..."
until mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PWD" --ssl=false --silent 2>/dev/null; do
  sleep 2
done

echo "Waiting for OpenSearch..."
until curl -s "http://$OPENSEARCH_HOST:$OPENSEARCH_PORT/_cluster/health" 2>/dev/null | grep -q '"status"'; do
  sleep 2
done

if ! grep -q 'db' app/etc/env.php 2>/dev/null; then
  sudo -u www-data php -d memory_limit=-1 bin/magento setup:install \
    --base-url=$BASE_URL/ \
    --db-host=$DB_HOST \
    --db-name=$DB_NAME \
    --db-user=$DB_USER \
    --db-password=$DB_PWD \
    --admin-firstname=admin \
    --admin-lastname=admin \
    --admin-email=admin@admin.com \
    --admin-user=admin \
    --admin-password=admin123 \
    --language=en_AU \
    --currency=AUD \
    --timezone=$TZ \
    --use-rewrites=1 \
    --backend-frontname=admin \
    --search-engine=$SEARCH_ENGINE \
    --opensearch-host=$OPENSEARCH_HOST \
    --opensearch-port=$OPENSEARCH_PORT

  sudo -u www-data php -d memory_limit=-1 bin/magento setup:upgrade
  sudo -u www-data php -d memory_limit=-1 bin/magento setup:di:compile
  sudo -u www-data php -d memory_limit=-1 bin/magento indexer:reindex
  sudo -u www-data php -d memory_limit=-1 bin/magento setup:static-content:deploy -f
  sudo -u www-data php -d memory_limit=-1 bin/magento cache:flush
fi

exec apache2-foreground
