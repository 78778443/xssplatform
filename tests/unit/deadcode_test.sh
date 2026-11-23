#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

# Obsolete symbols must be gone from live server code.
if grep -rnE 'PHPFetion|SendSMS\(|Smarty|Captcha|Image\.class|Pager\.class|SaeFetchurl|mailauth|validateKey' \
  --include='*.php' source api.php index.php authtest.php install scripts; then
  echo "obsolete code still present"
  exit 1
fi

# Obsolete files must not exist.
for f in \
  libs/Smarty.class.php \
  libs/SmartyBC.class.php \
  libs/Autoloader.php \
  themes/default/templates/header.html \
  source/api/PHPFetion.php \
  source/class/Image.class.php \
  source/class/Captcha.class.php \
  source/class/Pager.class.php \
  source/class/PHPMailer.class.php \
  source/class/Smtp.class.php \
  source/login.php \
  source/project.php \
  source/common.php \
  captcha.php ; do
  if [ -e "$f" ]; then
    echo "obsolete file still present: $f"
    exit 1
  fi
done

echo "dead code check passed"
