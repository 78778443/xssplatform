#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

NAME=${XSSP_TEST_DB_CONTAINER:-xssp-test-db}
PORT=${XSSP_TEST_DB_PORT:-33061}

if ! sudo docker ps -a --format '{{.Names}}' | grep -qx "$NAME"; then
  sudo docker run -d --name "$NAME" -e MYSQL_ROOT_PASSWORD=test \
    -p "127.0.0.1:${PORT}:3306" mysql:8.0
fi
for i in $(seq 1 60); do
  if sudo docker exec "$NAME" mysqladmin ping -uroot -ptest --silent >/dev/null 2>&1; then break; fi
  sleep 1
done
sudo docker exec -i "$NAME" mysql -uroot -ptest -e \
  "DROP DATABASE IF EXISTS xssplatform_test; CREATE DATABASE xssplatform_test DEFAULT CHARSET utf8;"
sudo docker exec -i "$NAME" mysql -uroot -ptest xssplatform_test < xssplatform.sql
cat > tests/config.test.php <<PHP
<?php
\$config = array(
 'dbHost' => '127.0.0.1:${PORT}', 'dbUser' => 'root', 'dbPwd' => 'test',
 'database' => 'xssplatform_test', 'charset' => 'utf8', 'tbPrefix' => 'oc_',
 'dbType' => 'mysql', 'register' => 'normal', 'urlroot' => 'http://127.0.0.1:18080',
 'urlrewrite' => false, 'timezone' => 'Asia/Shanghai', 'expires' => 3600, 'debug' => true,
 'show' => array('sitename' => 'XSS平台', 'sitedesc' => '', 'keywords' => '', 'description' => '', 'adminmail' => ''),
 'email' => array('host' => '', 'username' => '', 'password' => '', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'fromName' => 'Mailer'),
 'feishu' => array('keyword' => '最新消息', 'endpoint' => ''),
);
return \$config;
PHP
echo "test db ready"
