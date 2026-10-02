#!/usr/bin/env bash
#
# Runs the test suite against the real LocalWP WordPress site.
#
# Detects the Local PHP binary, MySQL socket and Mailpit (SMTP + HTTP API) for
# this site, then runs tests/run.php with a generated php.ini so wp_mail lands
# in Mailpit and the DB connection uses the Local socket.
#
# Usage:
#   tests/run.sh                 # all cases
#   tests/run.sh Otp             # only cases whose name contains "Otp"
#   tests/run.sh --verbose       # show stack traces on failure

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
WP_ROOT="$(dirname "$(dirname "$(dirname "$PLUGIN_DIR")")")"
LOCAL_CFG="${HOME}/.config/Local"

if [ ! -f "$WP_ROOT/wp-load.php" ]; then
  echo "WP root not found at $WP_ROOT (set WC_FA_WP_ROOT)." >&2
  exit 2
fi

# ── Resolve Local service paths via Python (JSON parsing) ──
read -r SITE_ID MAILPIT_HTTP MAILPIT_SMTP < <(
  WP_ROOT="$WP_ROOT" LOCAL_CFG="$LOCAL_CFG" python3 - <<'PY'
import json, os, sys
wp_root = os.path.realpath(os.environ["WP_ROOT"])
cfg = os.environ["LOCAL_CFG"]
sites = json.load(open(os.path.join(cfg, "sites.json")))
site_id = ""
http = "10000"
smtp = "10001"
for sid, site in sites.items():
    path = site.get("path", "")
    path = os.path.expanduser(path)
    candidate = os.path.realpath(os.path.join(path, "app", "public"))
    if os.path.realpath(candidate) == wp_root:
        site_id = sid
        svc = (site.get("services") or {}).get("mailpit", {})
        ports = svc.get("ports", {})
        http = str(ports.get("WEB", ["10000"])[0])
        smtp = str(ports.get("SMTP", ["10001"])[0])
        break
print(site_id, http, smtp)
PY
)

if [ -z "${SITE_ID}" ]; then
  echo "Could not map $WP_ROOT to a Local site in sites.json." >&2
  exit 2
fi

# ── Local PHP binary (prefer 8.2, fallback to any) ──
PHP_DIR=""
for candidate in "${LOCAL_CFG}"/lightning-services/php-8.2.29+0 \
                 "${LOCAL_CFG}"/lightning-services/php-8.2.27+1 \
                 "${LOCAL_CFG}"/lightning-services/php-8.2* \
                 "${LOCAL_CFG}"/lightning-services/php-8.*; do
  if [ -x "${candidate}/bin/linux/bin/php" ]; then
    PHP_DIR="${candidate}/bin/linux"
    break
  fi
done

if [ -z "${PHP_DIR}" ]; then
  echo "No LocalWP PHP binary found under ${LOCAL_CFG}/lightning-services." >&2
  exit 2
fi

# ── Mailpit binary ──
MAILPIT_BIN=""
for candidate in "${LOCAL_CFG}"/lightning-services/mailpit-*/bin/linux/mailpit; do
  if [ -x "${candidate}" ]; then
    MAILPIT_BIN="${candidate}"
    break
  fi
done

SOCKET="${LOCAL_CFG}/run/${SITE_ID}/mysql/mysqld.sock"
if [ ! -S "${SOCKET}" ]; then
  echo "MySQL socket not found at ${SOCKET} — is the site running?" >&2
  exit 2
fi

# ── Generated php.ini for this run ──
INI_FILE="$(mktemp -t wcfa-tests-XXXXXX.ini)"
cleanup() { rm -f "${INI_FILE}"; }
trap cleanup EXIT

{
  echo "memory_limit=1024M"
  echo "mysqli.default_socket=${SOCKET}"
  echo "pdo_mysql.default_socket=${SOCKET}"
  echo "error_reporting=E_ALL"
  echo "display_errors=1"
  if [ -n "${MAILPIT_BIN}" ] && [ -n "${MAILPIT_SMTP}" ]; then
    echo "sendmail_path=\"${MAILPIT_BIN} sendmail --smtp-addr=localhost:${MAILPIT_SMTP} mailhog@flywheel.local\""
  fi
} > "${INI_FILE}"

export LD_LIBRARY_PATH="${PHP_DIR}/shared-libs:${PHP_DIR}/bin/linux/lib${LD_LIBRARY_PATH:+:${LD_LIBRARY_PATH}}"
export WC_FA_MAILPIT_URL="http://127.0.0.1:${MAILPIT_HTTP}"
export WC_FA_WP_ROOT="${WP_ROOT}"

echo "PHP:      ${PHP_DIR}/bin/php ($("${PHP_DIR}/bin/php" -r 'echo PHP_VERSION;'))"
echo "Socket:   ${SOCKET}"
echo "Mailpit:  http://127.0.0.1:${MAILPIT_HTTP} (smtp :${MAILPIT_SMTP})"
echo ""

exec "${PHP_DIR}/bin/php" -c "${INI_FILE}" "${SCRIPT_DIR}/run.php" "$@"
