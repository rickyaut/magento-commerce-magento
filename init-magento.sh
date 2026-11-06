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
  sudo -u www-data php -d memory_limit=-1 bin/magento setup:static-content:deploy -f
  sudo -u www-data php -d memory_limit=-1 bin/magento indexer:reindex
else
  # Subsequent boots: pick up any new/updated modules
  sudo -u www-data php -d memory_limit=-1 bin/magento module:enable --all
  sudo -u www-data php -d memory_limit=-1 bin/magento setup:upgrade
fi

sudo -u www-data php -d memory_limit=-1 bin/magento cache:flush
# Fix invalid cache config (backend_options without backend key causes health check 500)
php -r "
\$env = include 'app/etc/env.php';
if (isset(\$env['cache']['frontend'])) {
  foreach (\$env['cache']['frontend'] as \$k => \$v) {
    if (isset(\$v['backend_options']) && !isset(\$v['backend'])) {
      \$env['cache']['frontend'][\$k] = ['id_prefix' => \$v['id_prefix'] ?? ''];
    }
  }
  file_put_contents('app/etc/env.php', '<?php' . PHP_EOL . 'return ' . var_export(\$env, true) . ';' . PHP_EOL);
}
"

exec apache2-foreground
