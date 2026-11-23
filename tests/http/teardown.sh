#!/usr/bin/env bash
set -euo pipefail
if [ -f /tmp/xssp-http.pid ]; then
  kill "$(cat /tmp/xssp-http.pid)" 2>/dev/null || true
  rm -f /tmp/xssp-http.pid
fi
