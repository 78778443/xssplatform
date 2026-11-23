#!/usr/bin/env bash
# Run the shadcn installer wizard end to end against a throwaway copy.
set -euo pipefail
cd "$(dirname "$0")/../.."
ROOT=$(pwd)
TMP=/tmp/xssp-install-ui
PIDFILE=/tmp/xssp-install-ui.pid
DBNAME=xssplatform_ui_e2e

cleanup() {
  if [ -f "$PIDFILE" ]; then
    kill "$(cat "$PIDFILE")" 2>/dev/null || true
    rm -f "$PIDFILE"
  fi
  sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" \
    mysql -uroot -ptest -e "DROP DATABASE IF EXISTS $DBNAME" 2>/dev/null || true
  rm -rf "$TMP"
}
trap cleanup EXIT

sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" \
  mysql -uroot -ptest -e "DROP DATABASE IF EXISTS $DBNAME" 2>/dev/null || true

rm -rf "$TMP"
rsync -a --exclude .git --exclude frontend/node_modules --exclude .superpowers ./ "$TMP/"
rm -f "$TMP/config.php" "$TMP/install/install.lock"
cd "$TMP"
setsid php -S 127.0.0.1:18082 deploy/router.php > /tmp/xssp-install-ui.log 2>&1 &
echo $! > "$PIDFILE"
sleep 1
cd "$ROOT/frontend"
INSTALL_BASE_URL=http://127.0.0.1:18082 npx playwright test e2e/install.spec.ts
