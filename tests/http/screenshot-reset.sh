#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" mysql -uroot -ptest "${XSSP_SCREENSHOT_DB:-xssplatform_http}" \
  < tests/fixtures/screenshot_seed.sql
