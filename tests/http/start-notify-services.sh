#!/usr/bin/env bash
# Start MailHog (SMTP 1025 / UI 8025) and the mock DingTalk endpoint (19090)
# used by tests/http/notify_test.php.
set -euo pipefail
cd "$(dirname "$0")/../.."

if ! sudo docker ps --format '{{.Names}}' | grep -qx xssp-mailhog; then
  sudo docker run -d --name xssp-mailhog -p 1025:1025 -p 8025:8025 mailhog/mailhog:latest >/dev/null
fi

if ! (exec 3<>/dev/tcp/127.0.0.1/19090) 2>/dev/null; then
  setsid php -S 127.0.0.1:19090 deploy/mock-dingding.php >/tmp/xssp-mock-dingding.log 2>&1 &
  echo $! > /tmp/xssp-mock-dingding.pid
fi

for i in $(seq 1 30); do
  if (exec 3<>/dev/tcp/127.0.0.1/1025) 2>/dev/null && (exec 3<>/dev/tcp/127.0.0.1/19090) 2>/dev/null; then
    echo "notify services ready (mailhog 1025/8025, mock dingtalk 19090)"
    exit 0
  fi
  sleep 1
done

echo "notify services did not become ready" >&2
exit 1
