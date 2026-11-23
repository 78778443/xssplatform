#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ -f /tmp/xssp-http-installed.pid ]; then
  kill "$(cat /tmp/xssp-http-installed.pid)" 2>/dev/null || true
  rm -f /tmp/xssp-http-installed.pid
fi

bash tests/setup-db.sh >/dev/null
rm -rf /tmp/xssp-http-installed
rsync -a --exclude .git --exclude frontend/node_modules --exclude .superpowers ./ /tmp/xssp-http-installed/
rm -f /tmp/xssp-http-installed/config.php /tmp/xssp-http-installed/install/install.lock
cd /tmp/xssp-http-installed
setsid php -S 127.0.0.1:18081 deploy/router.php > /tmp/xssp-http-installed.log 2>&1 &
echo $! > /tmp/xssp-http-installed.pid
sleep 1

status=$(curl -sS 'http://127.0.0.1:18081/api.php?action=install.status')
echo "$status" | grep -q '"installed":false' || { echo "unexpected install.status: $status"; exit 1; }
result=$(curl -sS -X POST 'http://127.0.0.1:18081/api.php?action=install.submit' \
  -H 'Content-Type: application/json' \
  -d '{"dbHost":"127.0.0.1:33061","dbUser":"root","dbPwd":"test","dbName":"xssplatform_http","username":"admin","password":"pw123456"}')
echo "$result" | grep -q '"ok":true' || { echo "install failed: $result"; exit 1; }
php "$(dirname "$0")/patch-notify-config.php" >/dev/null
# Demo reset wipes all content, so load it first and the byte fixtures last.
sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" mysql -uroot -ptest xssplatform_http < "$(dirname "$0")/../fixtures/screenshot_seed.sql" 2>/dev/null
sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" mysql -uroot -ptest xssplatform_http < "$(dirname "$0")/../fixtures/byte_seed.sql" 2>/dev/null
echo "installed test server ready"
