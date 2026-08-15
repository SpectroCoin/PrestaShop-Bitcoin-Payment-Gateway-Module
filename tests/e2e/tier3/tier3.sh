#!/usr/bin/env bash
# ============================================================================
# Tier 3 end-to-end test — a real shopper, in a real browser, through a real
# PrestaShop checkout.
#
# Tier 2 calls the module's client directly: it proves the payload and the
# callback contract, but never that the module appears at checkout. PrestaShop
# renders payment options from the paymentOptions hook at the last step of a
# multi-step checkout, so the only way to answer "can a shopper pay with this"
# is to walk a shopper there.
#
# The SpectroCoin API is stood in for by a stub answering as spectrocoin.com
# inside the compose network, over TLS signed by a CA generated here. No
# credentials, no live orders, no calls to the real API — and because the alias
# does the redirection, the module's own Config URLs are exercised as they ship.
#
# Usage:
#   ./tier3.sh          # run the full flow
#   ./tier3.sh --keep   # leave the stack running for inspection
# ============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../../.." && pwd)"
MODULE="spectrocoin"
KEEP=0

while [ $# -gt 0 ]; do
  case "$1" in
    --keep) KEEP=1; shift ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
pass() { printf '  \033[32mPASS\033[0m  %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$*"; FAILED=$((FAILED+1)); }
FAILED=0

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cd "$HERE"

# --------------------------------------------------------------------------
# 1. A CA and a certificate for spectrocoin.com.
# --------------------------------------------------------------------------
say "Generating certificates for the stub"
rm -rf artifacts && mkdir -p artifacts
rm -rf .certs && mkdir -p .certs
openssl req -x509 -newkey rsa:2048 -nodes -days 3650 \
  -keyout .certs/ca.key -out .certs/ca.crt \
  -subj "/CN=SpectroCoin Tier2 Test CA" >/dev/null 2>&1
openssl req -newkey rsa:2048 -nodes -keyout .certs/server.key -out .certs/server.csr \
  -subj "/CN=spectrocoin.com" >/dev/null 2>&1
printf 'subjectAltName=DNS:spectrocoin.com\n' > .certs/ext
openssl x509 -req -in .certs/server.csr -CA .certs/ca.crt -CAkey .certs/ca.key \
  -CAcreateserial -out .certs/server.crt -days 3650 -extfile .certs/ext >/dev/null 2>&1
chmod 644 .certs/*
[ -s .certs/server.crt ] && pass "issued a certificate for spectrocoin.com" \
  || fail "certificate generation failed"

# --------------------------------------------------------------------------
# 2. The stack.
# --------------------------------------------------------------------------
say "Starting PrestaShop and the API stub (first boot installs the shop)"
docker compose down -v >/dev/null 2>&1 || true
docker compose up -d --build --wait >/dev/null 2>&1

ps_exec() { docker compose exec -T prestashop "$@"; }
stub()    { docker compose exec -T spectrocoin "$@"; }
q()       { docker compose exec -T db mariadb -uroot -proot -N -B prestashop -e "$1" 2>/dev/null | tr -d '\r'; }

# Trust the test CA. Appended rather than replacing the bundle.
ps_exec sh -c 'cat /certs/ca.crt >> /etc/ssl/certs/ca-certificates.crt' >/dev/null 2>&1 || true
pass "PrestaShop responding"

# --------------------------------------------------------------------------
# 3. The module, built the way the release is built.
# --------------------------------------------------------------------------
say "Installing and configuring the module"
BUILD="$WORK/$MODULE"
mkdir -p "$BUILD"
( cd "$ROOT" && find . -maxdepth 1 -not -path '.' -not -path './.git' \
    -not -path './.github' -not -path './tests' -not -path './.gitignore' \
    -exec cp -r {} "$BUILD/" \; )
( cd "$BUILD" && composer install --no-dev --prefer-dist --optimize-autoloader \
    --no-interaction -q 2>/dev/null || php "$ROOT/../composer.phar" install \
    --no-dev --prefer-dist --optimize-autoloader --no-interaction -q )

docker compose cp "$BUILD" prestashop:/var/www/html/modules/spectrocoin >/dev/null 2>&1
ps_exec sh -c 'chown -R www-data:www-data /var/www/html/modules/spectrocoin'
ps_exec sh -c 'cd /var/www/html && php bin/console prestashop:module install spectrocoin 2>&1' \
  > "$WORK/install.log" 2>&1 || true

active=$(q "SELECT active FROM ps_module WHERE name='spectrocoin';")
if [ "$active" = "1" ]; then
  pass "module installed and active"
else
  fail "module is NOT installed. Installer said:"
  sed 's/^/        /' "$WORK/install.log" | grep -v '^\s*$' | head -8
fi

# Configure it as a merchant would through the module's settings screen.
ps_exec php -r '
  require "/var/www/html/config/config.inc.php";
  Configuration::updateValue("SPECTROCOIN_PROJECT_ID", "tier2-project");
  Configuration::updateValue("SPECTROCOIN_CLIENT_ID", "tier2-client");
  Configuration::updateValue("SPECTROCOIN_CLIENT_SECRET", "tier2-secret");
  echo "ok";' >/dev/null 2>&1 \
  && pass "credentials configured" || fail "could not configure the module"


# --------------------------------------------------------------------------
# 4. Make the shop shoppable without an account.
# --------------------------------------------------------------------------
say "Preparing the shop"

# The console commands above run as root and leave var/cache owned by root.
# Apache then cannot write its translation cache and every front-office page
# dies with an uncaught IOException - the shop looks empty rather than broken.
ps_exec sh -c 'rm -rf /var/www/html/var/cache/* 2>/dev/null; \
               chown -R www-data:www-data /var/www/html/var' >/dev/null 2>&1 || true
# Asked for through the shop's own hostname: PrestaShop redirects anything else
# to its configured domain, so curling localhost only proves it can redirect.
# The cache wipe above means the first hit pays for a Symfony/Twig rebuild, so
# give it a few tries rather than judging the shop dead on one slow response.
#
# The homepage is one very long single-line JSON blob followed by markup;
# `grep -q` (short-circuit-on-first-match) is unreliable against lines that
# long on BSD grep, so count matches instead of asking for a quiet yes/no.
front_office_ok=0
last_attempt=""
for _ in 1 2 3 4 5; do
  last_attempt=$(stub curl -sS -w '\nHTTPSTATUS:%{http_code} ERR:%{exitcode}' http://shop.test/ 2>&1)
  if [ "$(echo "$last_attempt" | grep -ci "product")" -gt 0 ]; then
    front_office_ok=1
    break
  fi
  sleep 3
done
if [ "$front_office_ok" -eq 1 ]; then
  pass "the front office renders"
else
  fail "the front office does not render - a shopper cannot reach checkout"
  echo "        --- last attempt ---"
  echo "$last_attempt" | tail -c 500 | sed 's/^/        /'
fi
ps_exec php -r '
  require "/var/www/html/config/config.inc.php";
  Configuration::updateValue("PS_GUEST_CHECKOUT_ENABLED", 1);
  Configuration::updateValue("PS_ORDER_PROCESS_TYPE", 0);
  Configuration::updateValue("PS_REGISTRATION_PROCESS_TYPE", 0);
  echo "ok";' >/dev/null 2>&1 \
  && pass "guest checkout enabled" || fail "could not enable guest checkout"

products=$(q "SELECT COUNT(*) FROM ps_product WHERE active=1;")
[ "${products:-0}" -gt 0 ] && pass "catalogue has $products products to buy" \
                           || fail "no active products to buy"

# The shop's default country (United Kingdom, out of the box) sits in a zone
# ("Europe (non-EU)") that ships with no carrier assigned to it. Left as-is,
# an address on that default country can never get a shipping option, so
# checkout dies at the delivery step before payment is ever reached - for
# every payment module alike, not just this one. That is shop configuration,
# not something the module controls, so fix it here rather than steering the
# test around a country the shop wasn't set up to sell to.
ps_exec php -r '
  require "/var/www/html/config/config.inc.php";
  $idZone = (int) Country::getIdZone((int) Configuration::get("PS_COUNTRY_DEFAULT"));
  $carrier = new Carrier(1);
  $carrier->addZone($idZone);
  echo "ok";' >/dev/null 2>&1 \
  && pass "a carrier now serves the default country's zone" \
  || fail "could not assign a carrier to the default country's zone"

# The shop must trust the CA this harness minted, or the module cannot reach
# the stub and checkout fails with a cURL error the shopper never sees.
if ps_exec sh -c 'curl -fsS -o /dev/null https://spectrocoin.com/__test/requests' >/dev/null 2>&1; then
  pass "the shop trusts the stub's certificate"
else
  fail "the shop cannot reach the stub over TLS - checkout will fail"
fi

stub curl -fsS -X POST http://localhost/__test/reset >/dev/null 2>&1

# --------------------------------------------------------------------------
# 5. The shopper.
# --------------------------------------------------------------------------
say "Walking a shopper through checkout"
pw() { docker compose exec -T playwright "$@"; }
# The image carries the browsers but not the client library; pin it to the
# image's own version so the two cannot drift apart.
pw sh -c 'cd /work && [ -d node_modules/playwright ] || npm --silent i playwright@1.50.0' \
  > "$WORK/npm.log" 2>&1 || true
pw sh -c 'node -e "require(\"playwright\")"' >/dev/null 2>&1 \
  && pass "browser client available" \
  || { fail "playwright module could not be installed:"; tail -4 "$WORK/npm.log" | sed 's/^/        /'; }

pw sh -c "SHOP_URL=http://shop.test MODULE_TITLE='SpectroCoin' node /work/checkout.mjs" \
  > "$WORK/browser.log" 2>&1 || true

# A browser run with no verdicts at all is a failure in itself - and pipefail
# would otherwise abort the script on the grep below.
if ! grep -aqE '^(PASS|FAIL)' "$WORK/browser.log"; then
  fail "the browser run produced no verdicts:"
  tail -12 "$WORK/browser.log" | sed 's/^/        /'
fi
grep -aE '^(PASS|FAIL|INFO)' "$WORK/browser.log" 2>/dev/null | while read -r line; do
  case "$line" in
    PASS*) printf '  \033[32mPASS\033[0m  %s\n' "${line#PASS }" ;;
    FAIL*) printf '  \033[31mFAIL\033[0m  %s\n' "${line#FAIL }" ;;
    INFO*) printf '  \033[33mNOTE\033[0m  %s\n' "${line#INFO }" ;;
  esac
done
browser_failures=$(grep -ac '^FAIL' "$WORK/browser.log" 2>/dev/null || true)
FAILED=$((FAILED + ${browser_failures:-0}))
[ "${browser_failures:-0}" -gt 0 ] && { echo "        --- browser log tail ---"; tail -12 "$WORK/browser.log" | sed 's/^/        /'; }

# --------------------------------------------------------------------------
# 6. What the shop and SpectroCoin ended up with.
# --------------------------------------------------------------------------
say "Verifying the order that resulted"
stub curl -fsS http://localhost/__test/requests > "$WORK/requests.json" 2>/dev/null
created=$(python3 - "$WORK/requests.json" <<'PYEOF'
import json,sys
for r in json.load(open(sys.argv[1])):
    if r["path"].endswith("/orders/create"):
        print(json.dumps(json.loads(r["body"] or "{}")))
        break
PYEOF
)
[ -n "$created" ] || created='{}'
field() { printf '%s' "$created" | python3 -c "import json,sys;print(json.load(sys.stdin).get('$1',''))" 2>/dev/null; }

[ -n "$(field orderId)" ] && pass "checkout produced a SpectroCoin order ($(field orderId))" \
  || fail "checkout never reached SpectroCoin - no create-order request arrived"

pending_state=$(q "SELECT value FROM ps_configuration WHERE name='SPECTROCOIN_PENDING';")
last=$(q "SELECT CONCAT(id_order,':',module,':',current_state) FROM ps_orders ORDER BY id_order DESC LIMIT 1;")
case "$last" in
  *:spectrocoin:$pending_state) pass "the shop recorded an order awaiting SpectroCoin payment ($last)" ;;
  "") fail "no PrestaShop order was created" ;;
  *) fail "the shop's latest order is '$last', expected module spectrocoin in state $pending_state" ;;
esac

if [ "$KEEP" -eq 1 ]; then
  echo -e "\nstack left running: add '127.0.0.1 shop.test' to /etc/hosts, then"
  echo    "http://shop.test:8094 (admin: tier2@example.com / tier2tier2)"
else
  docker compose down -v >/dev/null 2>&1 || true
  rm -rf .certs
fi

echo
[ "$FAILED" -eq 0 ] && echo "tier 3 PASSED" || echo "tier 3 FAILED ($FAILED check(s))"
exit $([ "$FAILED" -eq 0 ] && echo 0 || echo 1)
