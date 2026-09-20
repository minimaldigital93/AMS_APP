#!/bin/bash
#
# AMS_APP — khqr.cc → Bakong Open API cutover. RUN ON THE VPS, AS ROOT.
#
#   cd /var/www/AMS_APP && bash docs/cutover-bakong.sh
#
# Read this before running it. It is written to be read.
#
# WHAT IT DOES THAT CANNOT BE UNDONE:
#   • drops platform_payment_settings.khqrpay_secret and the merchant
#     equivalents. The COLUMNS come back on a rollback; the VALUES do not.
#     That is intended — they are credentials for a service you are leaving.
#   • (no longer applies) APP_KEY rotation — already done 2026-09-20; step 6
#     now refuses to repeat it once a Bakong token exists.
#
# It takes a full database dump first, and asks before each of those.
#
# STATE ON ams-vps AS OF 2026-09-20 — several steps below ALREADY RAN, by hand,
# during the APP_KEY outage. They are all idempotent, so re-running this script
# is safe, but know what it will and will not do:
#   • .env is already rewritten (KHQRPAY_* gone, BAKONG_* present) — step 4 is
#     a no-op, guarded by the `grep -q BAKONG_API_ENABLED` below.
#   • the khqr.cc scheduler is already disarmed — step 3 re-asserts it.
#   • APP_KEY was ALREADY ROTATED. Step 6 now detects an existing key and
#     defaults to skipping. Do not rotate again: see the warning there.
#   • both khqrpay_secret columns were already set to NULL (they had become
#     undecryptable when the old key was lost), so the migration in step 5
#     drops columns that are already empty.
#   • 0 khqr.cc payments were open, so step 2 has nothing to strand.
#
# WHAT IT DELIBERATELY DOES NOT DO:
#   • revoke anything at khqr.cc. Smart_sell shares that profile and will stop
#     taking payments the moment the token is revoked. Do that by hand, later,
#     once you have decided what happens to Smart_sell.
#   • run `cache:clear`. The Bakong cooldowns, backoffs and the "NBC says the
#     day is over" latch all live in the cache — flushing it hands the day a
#     fresh allowance and un-latches a refusal that is still true.

set -euo pipefail

APP_DIR="/var/www/AMS_APP"
BACKUP_DIR="/root/ams-cutover-$(date +%Y%m%d-%H%M%S)"

say()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[33m    %s\033[0m\n' "$*"; }
ask()  { read -r -p "    $1 [y/N] " r; [[ "$r" =~ ^[Yy]$ ]]; }

# ─────────────────────────────── 0. guards ───────────────────────────────

cd "$APP_DIR"
[[ -f artisan && -f .env ]] || { echo "Not an AMS_APP checkout: $APP_DIR"; exit 1; }
[[ $EUID -eq 0 ]] || { echo "Run as root — deploy.sh chowns to www-data."; exit 1; }

say "Current state"
echo "    branch:  $(git rev-parse --abbrev-ref HEAD)"
echo "    commit:  $(git rev-parse --short HEAD)"
echo "    backups: $BACKUP_DIR"

# ─────────────────────────── 1. back up first ────────────────────────────

say "Backing up .env and the database"
mkdir -p "$BACKUP_DIR"
cp .env "$BACKUP_DIR/env.backup"
chmod 600 "$BACKUP_DIR/env.backup"

# Credentials are read out of .env rather than typed, so they never appear in
# your shell history or in this script.
DB_DATABASE=$(grep -m1 '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"'"'"'')
DB_USERNAME=$(grep -m1 '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"'"'"'')
DB_PASSWORD=$(grep -m1 '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"'"'"'')

MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick \
    -u "$DB_USERNAME" "$DB_DATABASE" > "$BACKUP_DIR/db.sql"
chmod 600 "$BACKUP_DIR/db.sql"
echo "    $(du -h "$BACKUP_DIR/db.sql" | cut -f1) written"

# ──────────────── 2. the check that must happen BEFORE deploy ─────────────
#
# The khqr.cc webhook is deleted by this deploy. Any payment still open at
# khqr.cc loses the only thing that could still settle it automatically.

say "Open khqr.cc payments (must be settled BEFORE the webhook goes away)"
OPEN=$(php artisan tinker --execute="echo App\Models\KhqrPayment::where('provider','khqrpay')->whereIn('status',App\Enums\PaymentStatus::openValues())->count();" 2>/dev/null | tail -1 | tr -dc '0-9')
echo "    $OPEN open"

if [[ "${OPEN:-0}" -gt 0 ]]; then
    warn "Those can no longer be confirmed automatically after this deploy."
    warn "A subscription that WAS paid is settled by hand:"
    warn "  SuperAdmin → Accounts → change plan (the sanctioned out-of-band path)."
    warn "To see them:  php artisan khqr:expire-abandoned --hours=1 --dry-run"
    ask "Continue anyway?" || { echo "Stopped. Nothing changed."; exit 0; }
fi

# ─────────────────── 3. disarm khqr.cc before touching code ───────────────
#
# Done first and separately: it needs no deploy, and it stops the five-minute
# scheduler immediately even if you abort everything below.

say "Disarming the khqr.cc scheduler"
sed -i 's/^KHQRPAY_RECONCILE_ENABLED=.*/KHQRPAY_RECONCILE_ENABLED=false/' .env
sed -i 's/^KHQR_PAY_ENABLED=.*/KHQR_PAY_ENABLED=false/' .env
php artisan config:clear >/dev/null && php artisan config:cache >/dev/null
echo "    khqr:reconcile will skip from its next tick. Nothing reaches khqr.cc now."

# ──────────────────────────── 4. rewrite .env ─────────────────────────────

say "Rewriting .env for Bakong"

# Out: every khqr.cc variable. Note your PROFILE_ID and SECRET were already
# blank here — the real credential lives in the database and is dropped by the
# migration below, which is the one that actually matters.
sed -i '/^KHQR_PAY_ENABLED=/d; /^KHQRPAY_/d; /^# KHQRPay (khqr\.cc)/d; /^# KHQR quota guards/d' .env

# In: Bakong. Appended only if absent, so re-running this script is safe.
grep -q '^BAKONG_API_ENABLED=' .env || cat >> .env <<'ENVBLOCK'

# ─── Bakong Open API (NBC, direct) ───────────────────────────────────────
# Replaced khqr.cc 2026-09. The ACCESS TOKEN is NOT here: it lives encrypted
# in the database, per installation. See `php artisan bakong:token status`.
BAKONG_API_ENABLED=true
BAKONG_API_BASE_URL=https://api-bakong.nbc.gov.kh

# Must be the EXACT address the token was issued to. Used for the local token
# lookup, and SENT TO NBC on renewal — a wrong letter costs nothing today and
# silently fails to renew in ~90 days.
BAKONG_EMAIL=minimaldigial93@gmail.com
BAKONG_ORGANIZATION=MinimalDigital
BAKONG_PROJECT=AMS

# Blank on purpose: set the payout account in Superadmin → Payment Settings,
# which says which value is in force and where it came from.
BAKONG_ACCOUNT_ID=

# Our own ceiling, deliberately under NBC's ~100 so hitting it is a local
# event we can see rather than an upstream refusal. ~6 requests per checkout.
BAKONG_DAILY_REQUEST_LIMIT=80
BAKONG_VERIFY_COOLDOWN=60
BAKONG_QR_TTL=6
BAKONG_MAX_VERIFY_ATTEMPTS=8

# OFF for launch. Bakong sends no webhook, so this net is the only thing that
# catches a payer who closed the tab — but it is also pure spend on days when
# nobody does. Turn it on after watching `bakong:usage` for a few days.
BAKONG_RECONCILE_ENABLED=false
ENVBLOCK

echo "    done. Diff against the backup:"
diff <(grep -c . "$BACKUP_DIR/env.backup") <(grep -c . .env) >/dev/null || true
grep -E '^(BAKONG_API_ENABLED|BAKONG_RECONCILE_ENABLED|BAKONG_DAILY_REQUEST_LIMIT)=' .env | sed 's/^/    /'

# ───────────────────────────── 5. deploy ──────────────────────────────────

say "Deploying (git pull main → composer → migrate → caches)"
warn "The migration DROPS the khqr.cc credential columns. Values are not recoverable."
ask "Deploy now?" || { echo "Stopped. .env is updated; code is not. Backup: $BACKUP_DIR"; exit 0; }

./deploy.sh

# deploy.sh does not restart the worker, and it is running old code until it is.
if systemctl is-active --quiet ams-worker; then
    say "Restarting the queue worker"
    systemctl restart ams-worker
fi

# ──────────────────────── 6. APP_KEY (ALREADY ROTATED) ───────────────────
#
# Rotated by hand on 2026-09-20, after the key was lost out of .env entirely
# and every request 500'd with MissingAppKeyException. This step is kept so the
# script stays a complete account of the cutover — not because it is still due.

say "APP_KEY"

CURRENT_KEY=$(grep -m1 '^APP_KEY=' .env | cut -d= -f2-)

if [[ -n "$CURRENT_KEY" ]]; then
    echo "    A key is already set. Rotated 2026-09-20; nothing to do."

    # The reason this is now DANGEROUS rather than merely redundant.
    TOKENS=$(MYSQL_PWD="$DB_PASSWORD" mysql -N -u "$DB_USERNAME" -e \
        "select count(*) from bakong_tokens;" "$DB_DATABASE" 2>/dev/null | tr -dc '0-9')
    if [[ "${TOKENS:-0}" -gt 0 ]]; then
        warn "DO NOT ROTATE. $TOKENS Bakong token(s) are encrypted with this key."
        warn "Rotating would make them undecryptable and you would have to"
        warn "re-import from NBC. This is no longer the cheap operation the"
        warn "original version of this script described."
    fi
else
    warn "No APP_KEY set — the app will 500 on every request until there is one."
    warn "key:generate REGEX-REPLACES an existing APP_KEY= line; with the line"
    warn "absent it writes nothing and still reports success. So insert it first:"
    if ask "Insert a blank APP_KEY= line and generate a key now?"; then
        grep -q '^APP_KEY=' .env || sed -i '/^APP_ENV=/a APP_KEY=' .env
        php artisan key:generate --force
        php artisan config:clear >/dev/null && php artisan config:cache >/dev/null
        echo "    Generated. Every user is logged out once; bcrypt passwords are fine."
    fi
fi

# ─────────────────────────── 7. the token ─────────────────────────────────

say "Bakong access token"
echo "    It is stored encrypted in THIS host's database — .env does not carry it."
echo "    Importing costs ZERO Bakong requests."
warn "ONE HOST ONLY. If your MacBook or Mac mini also holds this token with"
warn "BAKONG_API_ENABLED=true, they share this server's 100/day allowance and the"
warn "meter on each shows only its own half. That is the khqr.cc problem again."

if ask "Import the token now? (you will paste it)"; then
    php artisan bakong:token import
else
    warn "Run later:  php artisan bakong:token import"
fi

# ──────────────────────────── 8. verify ───────────────────────────────────

say "Verifying — offline, costs nothing"
php artisan bakong:diagnose || true

say "Next, by hand"
cat <<'NEXT'
    1. Superadmin → Payment Settings
         · set the Bakong account ID (where subscription money lands)
         · the allowance meter should read 0 spent, token valid
    2. Take ONE real subscription payment, end to end.
    3. Only then: revoke the NBC token at khqr.cc.
         CHECK SMART_SELL FIRST — it shares that profile.
    4. Set BAKONG_API_ENABLED=false on the MacBook and the Mac mini.
    5. Watch `php artisan bakong:usage` for a few days before enabling
       BAKONG_RECONCILE_ENABLED.
NEXT

say "Done. Backups in $BACKUP_DIR"
