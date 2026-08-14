#!/usr/bin/env bash
# ============================================================================
# Tier 1 smoke test — install the packaged module into a real PrestaShop and
# prove it actually runs.
#
# Catches what unit tests cannot: an artifact shipped without its vendor tree,
# an autoloader that does not resolve, a fatal during module installation, or a
# payment module that installs but never registers its hooks.
#
# Usage:
#   ./smoke.sh                    # package the working tree the way release.yml does
#   ./smoke.sh --artifact x.zip   # test an arbitrary zip (e.g. a CI artifact)
#   ./smoke.sh --keep             # leave the stack running for inspection
# ============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
MODULE="spectrocoin"
ARTIFACT=""
KEEP=0

while [ $# -gt 0 ]; do
  case "$1" in
    --artifact) ARTIFACT="${2:-}"; shift 2 ;;
    --keep)     KEEP=1; shift ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
pass() { printf '  \033[32mPASS\033[0m  %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$*"; FAILED=$((FAILED+1)); }
FAILED=0

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# --------------------------------------------------------------------------
# 1. Obtain the artifact a merchant would install.
# --------------------------------------------------------------------------
say "Packaging artifact"
if [ -n "$ARTIFACT" ]; then
  cp "$ARTIFACT" "$WORK/module.zip"
  echo "  using supplied artifact $ARTIFACT"
else
  # Mirror release.yml exactly, including the tracked vendor tree.
  mkdir -p "$WORK/build/$MODULE"
  rsync -a --exclude='release_folder' --exclude='.git' --exclude='.github' \
        --exclude='README.txt' --exclude='README.md' --exclude='changelog.md' \
        --exclude='.gitignore' --exclude='.vscode' --exclude='tests' \
        "$ROOT/" "$WORK/build/$MODULE"
  ( cd "$WORK/build" && zip -qr "$WORK/module.zip" "$MODULE" )
  echo "  built from working tree ($(find "$WORK/build/$MODULE" -type f | wc -l | tr -d ' ') files)"
fi

unzip -qo "$WORK/module.zip" -d "$WORK/inspect"
[ -f "$WORK/inspect/$MODULE/vendor/autoload.php" ] \
  && pass "artifact contains vendor/autoload.php" \
  || fail "artifact has NO vendor/autoload.php - the module cannot run"

guzzle=$(find "$WORK/inspect/$MODULE/vendor/guzzlehttp/guzzle/src" -name '*.php' 2>/dev/null | wc -l | tr -d ' ')
if [ -f "$WORK/inspect/$MODULE/vendor/guzzlehttp/guzzle/src/Client.php" ] && [ "$guzzle" -gt 10 ]; then
  pass "artifact contains the HTTP client source ($guzzle files)"
else
  fail "artifact ships an EMPTY or partial guzzle tree ($guzzle php files)"
fi

[ -f "$WORK/inspect/$MODULE/$MODULE.php" ] \
  && pass "artifact contains the module entrypoint" \
  || fail "artifact is missing $MODULE.php"

# --------------------------------------------------------------------------
# 2. Real PrestaShop.
# --------------------------------------------------------------------------
say "Starting PrestaShop (first boot installs the shop, this is slow)"
cd "$HERE"
docker compose down -v >/dev/null 2>&1 || true
docker compose up -d --wait >/dev/null 2>&1
ps_exec() { docker compose exec -T prestashop "$@"; }
pass "PrestaShop $(ps_exec cat /var/www/html/install/install_version.php 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | head -1 || echo 8.x) responding"

# --------------------------------------------------------------------------
# 3. Install the artifact exactly as a merchant would.
# --------------------------------------------------------------------------
say "Installing the module"
docker compose cp "$WORK/module.zip" prestashop:/tmp/module.zip >/dev/null
ps_exec sh -c 'cd /var/www/html/modules && rm -rf spectrocoin && unzip -qo /tmp/module.zip && chown -R www-data:www-data spectrocoin' \
  && pass "module unpacked into modules/" || fail "module could not be unpacked"

# The console wording varies between PrestaShop versions, so the database is
# the source of truth below; the output is kept only to explain a failure.
ps_exec sh -c 'cd /var/www/html && php bin/console prestashop:module install spectrocoin 2>&1' \
  > "$WORK/install.log" 2>&1 || true
sed -i.bak 's/\x1b\[[0-9;]*m//g' "$WORK/install.log" 2>/dev/null || true
pass "install command executed"

# --------------------------------------------------------------------------
# 4. Assertions that only a real install can make.
# --------------------------------------------------------------------------
say "Verifying inside the running shop"

q() { docker compose exec -T db mariadb -uroot -proot -N -B prestashop -e "$1" 2>/dev/null | tr -d '\r'; }

active=$(q "SELECT active FROM ps_module WHERE name='spectrocoin';")
if [ "$active" = "1" ]; then
  pass "module installed and active"
else
  fail "module is NOT installed (ps_module.active='${active:-none}'). Installer said:"
  sed 's/^/        /' "$WORK/install.log" | grep -v '^\s*$' | head -8
fi

hooks=$(q "SELECT COUNT(*) FROM ps_hook_module hm JOIN ps_module m ON m.id_module=hm.id_module WHERE m.name='spectrocoin';")
[ "${hooks:-0}" -gt 0 ] \
  && pass "module registered $hooks hook(s)" \
  || fail "module registered NO hooks - it would never appear at checkout"

# paymentOptions is what puts the gateway on the checkout page.
payhook=$(q "SELECT COUNT(*) FROM ps_hook_module hm JOIN ps_module m ON m.id_module=hm.id_module JOIN ps_hook h ON h.id_hook=hm.id_hook WHERE m.name='spectrocoin' AND h.name LIKE 'paymentOptions';")
[ "${payhook:-0}" -gt 0 ] \
  && pass "registered on paymentOptions" \
  || fail "NOT registered on paymentOptions - no payment method at checkout"

# The classes must resolve through the module's own autoloader.
if ps_exec php -r '
  require "/var/www/html/modules/spectrocoin/vendor/autoload.php";
  exit(class_exists("SpectroCoin\\SCMerchantClient\\SCMerchantClient") ? 0 : 1);' >/dev/null 2>&1; then
  pass "SCMerchantClient resolves via autoload"
else
  fail "SCMerchantClient does NOT resolve - autoload or vendor is broken"
fi

if ps_exec php -r '
  require "/var/www/html/modules/spectrocoin/vendor/autoload.php";
  exit(class_exists("GuzzleHttp\\Client") ? 0 : 1);' >/dev/null 2>&1; then
  pass "GuzzleHttp\\Client resolves via autoload"
else
  fail "GuzzleHttp\\Client does NOT resolve - vendor tree is absent or stale"
fi

# --------------------------------------------------------------------------
# 5. Nothing may have been logged as a fatal.
# --------------------------------------------------------------------------
say "PHP error log"
log=$(ps_exec sh -c 'cat /var/www/html/var/logs/*.log /var/log/apache2/error.log 2>/dev/null || true')
ours=$(printf '%s\n' "$log" | grep -iE "fatal|uncaught|parse error" | grep -iE "spectrocoin|guzzle|class .* not found" || true)
[ -z "$ours" ] && pass "no fatals attributable to the module" \
  || { fail "fatals in the log:"; printf '%s\n' "$ours" | head -10; }

if [ "$KEEP" -eq 1 ]; then
  echo -e "\nstack left running: http://localhost:8081/adminsmoke (smoke@example.com / smokesmoke1)"
else
  docker compose down -v >/dev/null 2>&1 || true
fi

echo
[ "$FAILED" -eq 0 ] && echo "smoke test PASSED" || echo "smoke test FAILED ($FAILED check(s))"
exit $([ "$FAILED" -eq 0 ] && echo 0 || echo 1)
