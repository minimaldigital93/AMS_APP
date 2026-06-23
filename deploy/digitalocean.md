# AMS_APP — DigitalOcean Deployment Runbook

Migrate the live site off the Mac mini onto a DigitalOcean droplet, keeping
**`https://minimaldigital.dev/ams_app`** (the sub-path) working exactly as today.

| | Today (Mac mini) | After this runbook (droplet) |
|---|---|---|
| Edge / TLS | Cloudflare **Tunnel** (no open ports) | Cloudflare **proxied A record** + **Origin Cert** on nginx, Full (strict) |
| PHP runtime | `php artisan serve` (dev server) | **nginx + php-fpm** (production) |
| `/ams_app` URL handling | front-door nginx strips prefix, sets `X-Forwarded-Prefix` | **identical** — same contract, just on the droplet |
| `/smart_sell` | Next.js on the Mac | Next.js on the droplet (`:3000`) |
| `/` | "coming soon" placeholder | same placeholder |
| Process mgmt | launchd + `brew services` | **systemd** |

> The whole point: only the **front door** and the **PHP runtime** change. The
> sub-path mechanics (`APP_URL=…/ams_app`, `ASSET_URL=/ams_app`,
> `SESSION_PATH=/ams_app`, `FORCE_HTTPS=true`, `X-Forwarded-Prefix /ams_app`)
> are preserved, so links, assets, and sessions behave the same.

Config files referenced below already live in this repo under `deploy/`:

| Live location on droplet | Source in repo |
|---|---|
| `/etc/nginx/sites-available/minimaldigital.dev` | `deploy/nginx/do-minimaldigital.dev.conf` |
| `/etc/nginx/sites-available/ams_app-backend` | `deploy/nginx/do-ams_app-backend.conf` |
| `/etc/systemd/system/ams_app-queue.service` | `deploy/systemd/ams_app-queue.service` |
| `/etc/systemd/system/ams_app-scheduler.{service,timer}` | `deploy/systemd/ams_app-scheduler.*` |
| `/etc/systemd/system/smartsell.service` | `deploy/systemd/smartsell.service` |

---

## 0. Variables

| Placeholder | Value |
|---|---|
| Domain | `minimaldigital.dev` (+ `www`) |
| Droplet | Ubuntu **24.04 LTS**, **2 GB RAM / 1 vCPU minimum** (2 vCPU nicer for builds) |
| `DROPLET_IP` | the droplet's public IPv4 (fill in after creating it) |
| AMS_APP path | `/var/www/AMS_APP` |
| Smart_sell path | `/var/www/Smart_sell` |
| AMS_APP repo | `https://github.com/minimaldigital93/AMS_APP.git` |
| Smart_sell repo | _your Smart_sell repo URL_ (fill in — see Step 8) |
| DB name | `AMS_APP` (matches current `.env`) |
| DB user | `ams_user` (dedicated; replaces `root`) |

**What you need from the Mac before starting (the live secrets — not in git):**
- the current `~/AMS_APP/.env` (has `APP_KEY`, DB/Redis/KHQR secrets)
- a fresh MySQL dump of the `AMS_APP` database
- the `storage/app` uploads directory

Grab them now (run **on the Mac**):

```bash
cd ~/AMS_APP
# 1) DB dump
mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
  -u root -p AMS_APP > /tmp/ams_app.sql
# 2) uploads (skip if storage/app/public is empty)
tar czf /tmp/ams_app-storage.tgz storage/app
# 3) keep .env handy
cp .env /tmp/ams_app.env
```

You'll `scp` these to the droplet in Step 5.

---

## 1. Create & secure the droplet

Create the droplet in the DO console (Ubuntu 24.04, add your SSH key). Then SSH
in as root and harden it:

```bash
ssh root@DROPLET_IP

apt update && apt upgrade -y

# Non-root sudo user
adduser deployer
usermod -aG sudo deployer

# 2 GB swap — composer/npm builds OOM on small droplets without it
fallocate -l 2G /swapfile && chmod 600 /swapfile
mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
ufw status
```

---

## 2. Install the stack

```bash
# PHP 8.3 from ondrej PPA
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-redis

apt install -y mysql-server redis-server git unzip

# Composer (global)
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php

# Node.js 20 LTS (for Vite build + Next.js)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

php -v && composer --version && node -v && nginx -v
```

Confirm the php-fpm socket path matches the nginx config (`/run/php/php8.3-fpm.sock`):

```bash
ls /run/php/
```

---

## 3. Redis password (match `.env`)

The app's `.env` sets `REDIS_PASSWORD`. Set the same password on the droplet's
Redis (use the value from `/tmp/ams_app.env`):

```bash
sed -i 's/^# *requirepass .*/requirepass YOUR_REDIS_PASSWORD/' /etc/redis/redis.conf
systemctl restart redis-server
redis-cli -a YOUR_REDIS_PASSWORD ping   # -> PONG
```

> Sessions/cache/queue are actually on `database` (see `.env`), so Redis is only
> used where the code calls it explicitly. Setting the password keeps parity and
> avoids surprises.

---

## 4. Create the MySQL database & user

```bash
mysql_secure_installation   # set a root password, answer the prompts

mysql
```

```sql
CREATE DATABASE AMS_APP CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ams_user'@'localhost' IDENTIFIED BY 'STRONG_DB_PASSWORD';
GRANT ALL PRIVILEGES ON AMS_APP.* TO 'ams_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Deploy AMS_APP code + data

Copy the secrets/data from the Mac (run **on the Mac**, or `scp` from there):

```bash
scp /tmp/ams_app.sql /tmp/ams_app-storage.tgz /tmp/ams_app.env deployer@DROPLET_IP:/tmp/
```

Back **on the droplet**:

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/minimaldigital93/AMS_APP.git /var/www/AMS_APP
sudo chown -R $USER:$USER /var/www/AMS_APP
cd /var/www/AMS_APP

composer install --no-dev --optimize-autoloader

# Bring over the live .env, then edit DB creds for the new dedicated user
cp /tmp/ams_app.env .env
nano .env
```

In `.env`, keep everything (especially `APP_KEY`, the `/ams_app` URLs, KHQR
secrets) and change only the DB credentials to the droplet's:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://minimaldigital.dev/ams_app
ASSET_URL=/ams_app
FORCE_HTTPS=true
SESSION_PATH=/ams_app
SESSION_DOMAIN=.minimaldigital.dev

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AMS_APP
DB_USERNAME=ams_user
DB_PASSWORD=STRONG_DB_PASSWORD
```

Import the database and restore uploads:

```bash
mysql -u ams_user -p AMS_APP < /tmp/ams_app.sql
tar xzf /tmp/ams_app-storage.tgz        # restores storage/app/*

# Build assets, link storage, cache config
npm ci && npm run build
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> The DB is already migrated (it's a copy of live), so **don't** run
> `migrate --force` here unless your repo has migrations newer than the dump.

Permissions so www-data (php-fpm/nginx) can write:

```bash
sudo chown -R www-data:www-data /var/www/AMS_APP
sudo find /var/www/AMS_APP -type f -exec chmod 644 {} \;
sudo find /var/www/AMS_APP -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwx /var/www/AMS_APP/storage /var/www/AMS_APP/bootstrap/cache
```

---

## 6. nginx (front door + Laravel backend)

```bash
sudo cp /var/www/AMS_APP/deploy/nginx/do-minimaldigital.dev.conf /etc/nginx/sites-available/minimaldigital.dev
sudo cp /var/www/AMS_APP/deploy/nginx/do-ams_app-backend.conf      /etc/nginx/sites-available/ams_app-backend

sudo ln -s /etc/nginx/sites-available/minimaldigital.dev /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/ams_app-backend     /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
```

The front door references the Cloudflare Origin Cert (created in Step 7) at
`/etc/ssl/cloudflare/minimaldigital.dev.{pem,key}` — install those **before**
reloading nginx, or `nginx -t` will fail on the missing cert.

---

## 7. Cloudflare: Origin Cert + DNS cutover

In the **mycoding5555** Cloudflare account (zone `minimaldigital.dev`, NS
`elsa`/`theo` — see [the DNS gotcha](#dns-gotcha)):

1. **SSL/TLS → Origin Server → Create Certificate** (default RSA, hostnames
   `minimaldigital.dev`, `*.minimaldigital.dev`, 15-year). Save the two PEM blocks
   onto the droplet:

   ```bash
   sudo mkdir -p /etc/ssl/cloudflare
   sudo nano /etc/ssl/cloudflare/minimaldigital.dev.pem   # paste the certificate
   sudo nano /etc/ssl/cloudflare/minimaldigital.dev.key   # paste the private key
   sudo chmod 600 /etc/ssl/cloudflare/minimaldigital.dev.key
   ```

2. **SSL/TLS → Overview → Full (strict)**.

3. **DNS** — replace the tunnel routing with A records pointing at the droplet,
   both **Proxied (orange cloud)**:
   - delete the `CNAME minimaldigital.dev -> <tunnel-id>.cfargotunnel.com` (and the
     `www` one) that the tunnel created
   - add `A  minimaldigital.dev  -> DROPLET_IP`  (Proxied)
   - add `A  www                 -> DROPLET_IP`  (Proxied)

4. Test nginx and bring it up:

   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

5. Verify DNS resolves to Cloudflare (proxied IPs, not the droplet directly):

   ```bash
   dig +short minimaldigital.dev
   ```

Visit `https://minimaldigital.dev/ams_app` — the app should load over HTTPS with
assets and login working. `https://minimaldigital.dev/` shows the placeholder.

---

## 8. Smart_sell (Next.js) on the droplet

```bash
sudo git clone YOUR_SMARTSELL_REPO_URL /var/www/Smart_sell
sudo chown -R $USER:$USER /var/www/Smart_sell
cd /var/www/Smart_sell

# Restore its .env from the Mac if it has one (scp ~/Smart_sell/.env first)
npm ci
npm run build

sudo chown -R www-data:www-data /var/www/Smart_sell
sudo cp /var/www/AMS_APP/deploy/systemd/smartsell.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now smartsell
```

> Confirm Smart_sell's `basePath` is `/smart_sell` (it was on the Mac). Test
> `https://minimaldigital.dev/smart_sell`. If you don't have the repo handy,
> skip this step and revisit — the rest of the site works without it.

---

## 9. Background services (queue + scheduler)

```bash
sudo cp /var/www/AMS_APP/deploy/systemd/ams_app-queue.service       /etc/systemd/system/
sudo cp /var/www/AMS_APP/deploy/systemd/ams_app-scheduler.service   /etc/systemd/system/
sudo cp /var/www/AMS_APP/deploy/systemd/ams_app-scheduler.timer     /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ams_app-queue
sudo systemctl enable --now ams_app-scheduler.timer

# Status
systemctl status ams_app-queue --no-pager
systemctl list-timers ams_app-scheduler.timer --no-pager
```

(`QUEUE_CONNECTION=database` → the queue worker is required for jobs to run.)

---

## 10. Cut over & retire the Mac

Once the droplet serves all three paths correctly:

```bash
# On the Mac — stop hosting (tunnel first, then backends)
launchctl bootout gui/$(id -u)/com.minimaldigital.cloudflared
launchctl bootout gui/$(id -u)/com.minimaldigital.smartsell
launchctl bootout gui/$(id -u)/com.minimaldigital.laravel
brew services stop nginx
```

Keep the Mac's DB and `.env` around for a few days as a rollback until you're
confident. To roll back, re-point the Cloudflare DNS to the tunnel CNAME and
restart the Mac services.

---

## Redeploying later (after pushing to GitHub)

```bash
cd /var/www/AMS_APP
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan down --render="errors::503"
sudo -u www-data php artisan migrate --force
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache \
  && sudo -u www-data php artisan view:cache && sudo -u www-data php artisan event:cache
sudo systemctl restart ams_app-queue     # pick up new code in the worker
sudo -u www-data php artisan up
```

The repo's `composer deploy` script does the migrate→cache→build→up part; the
only extra on a php-fpm host is `systemctl restart ams_app-queue` so the
long-running worker reloads the new code. (php-fpm itself serves fresh PHP per
request — no restart needed for plain code changes.)

---

## <a id="dns-gotcha"></a>DNS gotcha (carry-over from the Mac setup)

DNS lives in the **mycoding5555@gmail.com** Cloudflare account; zone nameservers
are `elsa.ns.cloudflare.com` / `theo.ns.cloudflare.com`, and Namecheap must
delegate to those. An older duplicate zone in `Minimaldigital93@gmail.com`
(`holly`/`rayden`) can cause **Cloudflare Error 1000** if the registrar points
back there. Fix: set Namecheap NS to elsa/theo, then in the mycoding5555 account
click **Check nameservers now**.

---

## Troubleshooting

- **502 Bad Gateway on `/ams_app`** → the `:8000` backend isn't answering. Check
  `sudo nginx -t`, that `ams_app-backend` is enabled, and the php-fpm socket
  path: `ls /run/php/` vs the `fastcgi_pass` in `do-ams_app-backend.conf`.
  `sudo systemctl restart php8.3-fpm nginx`.
- **Assets 404 / unstyled page** → `ASSET_URL=/ams_app` missing, `npm run build`
  not run, or the `^~ /ams_app/build/` alias path is wrong. Confirm
  `/var/www/AMS_APP/public/build/` exists.
- **Login bounces / "419 Page Expired"** → session cookie path. Confirm
  `SESSION_PATH=/ams_app` and `SESSION_DOMAIN=.minimaldigital.dev` in `.env`, then
  `php artisan config:cache`.
- **Redirect drops `/ams_app` (lands at root)** → `X-Forwarded-Prefix` not
  reaching Laravel or `absolute_redirect off` missing. Both are in the shipped
  configs; re-check they're installed.
- **Cloudflare 526 (invalid cert)** → Origin Cert not installed at
  `/etc/ssl/cloudflare/…` or SSL mode isn't **Full (strict)**.
- **500 / blank** → permissions on `storage`/`bootstrap/cache` or stale cache.
  Re-run the chown/chmod block, `php artisan optimize:clear`, check
  `storage/logs/laravel.log`.
- **Queued jobs not running** → `systemctl status ams_app-queue`; tail
  `storage/logs/queue.log`.
