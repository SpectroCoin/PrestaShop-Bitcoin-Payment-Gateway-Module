#!/usr/bin/env bash
# ============================================================================
# Tier 2 end-to-end test — configure the module, place a real PrestaShop order
# through it, deliver callbacks, and assert what the shop actually does.
#
# Tier 1 proves the module installs and registers. This proves it *works*: that
# the order we send SpectroCoin describes the shop's order, and that every
# status on the wire moves the shop's order where it should — or deliberately
# leaves it alone.
#
# The SpectroCoin API is stood in for by a stub answering as spectrocoin.com
# inside the compose network, over TLS signed by a CA generated here. No
# credentials, no live orders, no calls to the real API — and because the alias
# does the redirection, the module's own Config URLs are exercised as they ship.
#
# Usage:
#   ./tier2.sh          # run the full flow
#   ./tier2.sh --keep   # leave the stack running for inspection
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
# 4. Place a real order through the module.
# --------------------------------------------------------------------------
say "Placing an order through the module"
stub curl -fsS -X POST http://localhost/__test/reset >/dev/null 2>&1
docker compose cp place-order.php prestashop:/tmp/place-order.php >/dev/null 2>&1
ps_exec sh -c 'rm -f /tmp/tier2-order.json; php /tmp/place-order.php' > "$WORK/place.log" 2>&1 || true

ps_exec sh -c 'cat /tmp/tier2-order.json 2>/dev/null' > "$WORK/order.json" 2>/dev/null || true
ofield() { python3 -c "import json,sys;print(json.load(open('$WORK/order.json')).get('$1',''))" 2>/dev/null; }
PS_ORDER=$(ofield order_id); PS_ORDER=${PS_ORDER:-0}
PS_TOTAL=$(ofield total)
PS_CCY=$(ofield currency)

if [ "${PS_ORDER:-0}" -gt 0 ]; then
  pass "PrestaShop order #$PS_ORDER created"
else
  fail "no order was created. Output was:"; sed 's/^/        /' "$WORK/place.log" | head -10
fi

pending_state=$(q "SELECT value FROM ps_configuration WHERE name='SPECTROCOIN_PENDING';")
state=$(q "SELECT current_state FROM ps_orders WHERE id_order=${PS_ORDER:-0};")
[ -n "$state" ] && [ "$state" = "$pending_state" ] \
  && pass "order is left awaiting payment" \
  || fail "order state is '$state', expected the module's pending state '$pending_state'"

# --------------------------------------------------------------------------
# 5. What the module actually sent us.
# --------------------------------------------------------------------------
say "Inspecting the request the module sent"
stub curl -fsS http://localhost/__test/requests > "$WORK/requests.json" 2>/dev/null

created=$(python3 - "$WORK/requests.json" <<'PYEOF'
import json,sys
for r in json.load(open(sys.argv[1])):
    if r["path"].endswith("/orders/create"):
        print(json.dumps({**json.loads(r["body"] or "{}"), "_ua": r["user_agent"]}))
        break
PYEOF
)
[ -n "$created" ] || created='{}'
field() { printf '%s' "$created" | python3 -c "import json,sys;print(json.load(sys.stdin).get('$1',''))" 2>/dev/null; }

[ -n "$(field orderId)" ] && pass "an order was sent to SpectroCoin" \
  || fail "no create-order request reached SpectroCoin"

case "$(field orderId)" in
  "$PS_ORDER"-*) pass "orderId carries the shop's order id" ;;
  *) fail "orderId '$(field orderId)' does not start with $PS_ORDER-" ;;
esac

if [ -n "$PS_CCY" ] && [ "$(field receiveCurrencyCode)" = "$PS_CCY" ]; then
  pass "order was sent in the shop's currency ($PS_CCY)"
else
  fail "receiveCurrencyCode was '$(field receiveCurrencyCode)', order is in '${PS_CCY:-none}'"
fi

# Compared as numbers: the module sends 61.8 where SQL reports 61.80.
if [ -n "$PS_TOTAL" ] && python3 -c "
import sys
sys.exit(0 if abs(float('$(field receiveAmount)' or 'nan') - float('$PS_TOTAL')) < 0.005 else 1)" 2>/dev/null; then
  pass "order was sent for the shop's total ($PS_TOTAL)"
else
  fail "receiveAmount was '$(field receiveAmount)', order total is '${PS_TOTAL:-none}'"
fi

case "$(field callbackUrl)" in
  *spectrocoin*callback*|*controller=callback*) pass "callbackUrl points at the module's endpoint" ;;
  *) fail "unexpected callbackUrl: '$(field callbackUrl)'" ;;
esac

[ "$(field projectId)" = "tier2-project" ] \
  && pass "projectId is the configured one" \
  || fail "projectId was '$(field projectId)'"

case "$(field _ua)" in
  SpectroCoin-PrestaShop/*) pass "identifies itself as $(field _ua)" ;;
  *) fail "User-Agent was '$(field _ua)', expected SpectroCoin-PrestaShop/<version>" ;;
esac

UUID=$(stub sh -c 'php -r "\$s=json_decode(file_get_contents(\"/tmp/stub-state.json\"),true); echo array_key_first(\$s[\"orders\"]);"' 2>/dev/null)
[ -n "$UUID" ] && pass "SpectroCoin order created (uuid ${UUID:0:8}…)" \
               || fail "no SpectroCoin order was created"

# --------------------------------------------------------------------------
# 6. Deliver callbacks and assert what the shop does with each status.
# --------------------------------------------------------------------------
say "Delivering callbacks for every status on the wire"

CB="http://shop.test/index.php?fc=module&module=spectrocoin&controller=callback"
# Delivered from the stub container: in production the callback comes from
# SpectroCoin's server, and only a container on this network resolves shop.test.
shopcurl() { docker compose exec -T spectrocoin curl "$@"; }

patch_order() {
  stub curl -fsS -X POST -H 'Content-Type: application/json' -d "$1" \
    http://localhost/__test/status >/dev/null 2>&1
}

reset_order() {
  q "UPDATE ps_orders SET current_state=$pending_state WHERE id_order=$PS_ORDER;" >/dev/null 2>&1
}

# Expected states, resolved from the shop's own configuration.
S_PAYMENT=$(q "SELECT value FROM ps_configuration WHERE name='PS_OS_PAYMENT';")
S_ERROR=$(q "SELECT value FROM ps_configuration WHERE name='PS_OS_ERROR';")
S_CANCELED=$(q "SELECT value FROM ps_configuration WHERE name='PS_OS_CANCELED';")

check_status() {
  local status="$1" want_state="$2" note="${3:-}"
  reset_order
  patch_order "{\"uuid\":\"$UUID\",\"status\":\"$status\"}"
  local code got
  code=$(shopcurl -s -o /dev/null -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' \
    -d "{\"id\":\"$UUID\",\"merchantApiId\":\"tier2-api\"}" "$CB")
  got=$(q "SELECT current_state FROM ps_orders WHERE id_order=$PS_ORDER;")
  if [ "$code" = "200" ] && [ "$got" = "$want_state" ]; then
    pass "$status -> state $want_state${note:+ ($note)}"
  else
    fail "$status gave HTTP $code and state '$got', expected 200 and '$want_state'${note:+ ($note)}"
  fi
}

check_status NEW     "$pending_state" "no change"
check_status PENDING "$pending_state" "no change"
check_status PAID    "$S_PAYMENT"     "payment accepted"
check_status FAILED          "$S_ERROR"
check_status CANCELLED       "$S_ERROR"
check_status REJECTED        "$S_ERROR"
check_status INVALID_PAYMENT "$S_ERROR"
check_status EXPIRED         "$S_CANCELED"

# Informational statuses report on a payment already under way. The order must
# be left exactly as it was: transitioning here would either fulfil an order
# that was not paid in full, or reverse one the merchant already settled.
for s in PARTIAL_PAYMENT UNDERPAID LATE_CRYPTO_PAYMENT PENDING_LATE_CRYPTO_PAYMENT \
         PROCESSING_REFUND REFUNDED REJECTED_REFUND TEST TEST_PAID TEST_EXPIRED; do
  check_status "$s" "$pending_state" "informational, no change"
done

# --------------------------------------------------------------------------
# 7. The callback endpoint is a public URL. It must refuse the obvious abuse.
# --------------------------------------------------------------------------
say "Callback endpoint guards"

code=$(shopcurl -s -o /dev/null -w '%{http_code}' "$CB")
[ "$code" = "405" ] && pass "GET is refused (405)" \
                    || fail "GET returned $code, expected 405 - the callback must be POST-only"

patch_order "{\"uuid\":\"$UUID\",\"orderId\":\"999999-aaaaaa\"}"
code=$(shopcurl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
  -d "{\"id\":\"$UUID\",\"merchantApiId\":\"tier2-api\"}" "$CB")
if [ "$code" = "404" ] || [ "$code" = "400" ]; then
  pass "a callback for an unknown order is refused ($code)"
else
  fail "unknown order returned $code, expected 404 or 400"
fi

# An order placed through a different module must not be settleable here.
OTHER=$(q "SELECT id_order FROM ps_orders WHERE module<>'spectrocoin' ORDER BY id_order LIMIT 1;")
if [ -n "$OTHER" ]; then
  before=$(q "SELECT current_state FROM ps_orders WHERE id_order=$OTHER;")
  patch_order "{\"uuid\":\"$UUID\",\"status\":\"PAID\",\"orderId\":\"$OTHER-aaaaaa\"}"
  code=$(shopcurl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
    -d "{\"id\":\"$UUID\",\"merchantApiId\":\"tier2-api\"}" "$CB")
  after=$(q "SELECT current_state FROM ps_orders WHERE id_order=$OTHER;")
  if [ "$code" = "400" ] && [ "$before" = "$after" ]; then
    pass "a callback cannot settle an order paid through another module (400)"
  else
    fail "callback returned $code and moved order #$OTHER from '$before' to '$after'"
  fi
else
  fail "no order from another module to test against"
fi

# Restore the mapping, then disagree about the currency.
patch_order "{\"uuid\":\"$UUID\",\"orderId\":\"$PS_ORDER-aaaaaa\",\"receiveCurrencyCode\":\"XXX\",\"status\":\"PAID\"}"
reset_order
code=$(shopcurl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
  -d "{\"id\":\"$UUID\",\"merchantApiId\":\"tier2-api\"}" "$CB")
now=$(q "SELECT current_state FROM ps_orders WHERE id_order=$PS_ORDER;")
if [ "$code" = "400" ] && [ "$now" = "$pending_state" ]; then
  pass "a settlement in the wrong currency is refused (400)"
else
  fail "currency mismatch returned $code and left the order in state '$now'"
fi

if [ "$KEEP" -eq 1 ]; then
  echo -e "\nstack left running: add '127.0.0.1 shop.test' to /etc/hosts, then"
  echo    "http://shop.test:8087/admintier2 (tier2@example.com / tier2tier2)"
else
  docker compose down -v >/dev/null 2>&1 || true
  rm -rf .certs
fi

echo
[ "$FAILED" -eq 0 ] && echo "tier 2 PASSED" || echo "tier 2 FAILED ($FAILED check(s))"
exit $([ "$FAILED" -eq 0 ] && echo 0 || echo 1)
