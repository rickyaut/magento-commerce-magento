#!/bin/bash
# Usage:
#   ./dev-reload.sh            # templates/layout/JS/CSS only — just flush cache
#   ./dev-reload.sh compile    # PHP class changes (new classes, DI, plugins)
#   ./dev-reload.sh full       # new module or registration.php changes

CONTAINER=$(docker ps -qf name=web)
CMD="docker exec $CONTAINER sudo -u www-data php -d memory_limit=-1 bin/magento"

case "${1:-}" in
  compile)
    echo "→ setup:upgrade + di:compile + cache:flush"
    $CMD setup:upgrade
    $CMD setup:di:compile
    $CMD cache:flush
    ;;
  full)
    echo "→ setup:upgrade + di:compile + static:deploy + cache:flush"
    $CMD setup:upgrade
    $CMD setup:di:compile
    $CMD setup:static-content:deploy -f
    $CMD cache:flush
    ;;
  *)
    echo "→ cache:flush (templates/layout/JS/CSS)"
    $CMD cache:flush
    ;;
esac
