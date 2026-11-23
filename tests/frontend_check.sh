#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../frontend"
npm test
npx tsc --noEmit
npm run build
