#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
# Restore fixtures used by the PHP HTTP regression tests after the demo reset.
sudo docker exec -i "${XSSP_TEST_DB_CONTAINER:-xssp-test-db}" mysql -uroot -ptest "${XSSP_SCREENSHOT_DB:-xssplatform_http}" \
  < tests/fixtures/byte_seed.sql
