#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

bash tests/setup-db.sh >/dev/null
rm -rf /tmp/xssp-http
rsync -a --exclude .git --exclude frontend/node_modules --exclude .superpowers ./ /tmp/xssp-http/
cp tests/config.test.php /tmp/xssp-http/config.php
mkdir -p /tmp/xssp-http/install
touch /tmp/xssp-http/install/install.lock
cd /tmp/xssp-http
setsid php -S 127.0.0.1:18080 deploy/router.php > /tmp/xssp-http.log 2>&1 &
echo $! > /tmp/xssp-http.pid
sleep 1
echo "http test server ready"
