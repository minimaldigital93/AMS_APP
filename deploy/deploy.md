# Deploy & Hosting Runbook — AMS_APP

This app is **self-hosted on this Mac**. There is no cloud server: the Mac runs
the Laravel app, and a Cloudflare Tunnel pipes public traffic from
**minimaldigital.dev** down to it.

## Development vs. host: who does what

There are two checkouts of this repo and one shared GitHub remote:

```
   Mac (host):    AMS_APP_dev  ─┐
                                ├─ git push ─►  GitHub: minimaldigital93/AMS_APP  (origin/main)
   Other MacBook: AMS_APP_dev  ─┘                          │
                                                           │ git pull
                                                           ▼
   Mac (host):    AMS_APP  ──────────────────────►  LIVE SITE (launchd + cloudflared)
```

- **`AMS_APP_dev`** — where you write code. Both this Mac and the other MacBook
  develop here, then `git push` to `origin` (minimaldigital93/AMS_APP).
- **`AMS_APP`** — the server checkout on this Mac. It **only pulls** from
  `origin` and deploys. **Never edit code in here** — it would conflict with the
  next `git pull`.

Both checkouts have exactly one remote, `origin`, pointing at
`minimaldigital93/AMS_APP`.

### Develop & push (run in `AMS_APP_dev`, on either Mac)

```bash
git pull                          # get latest before you start
# ...edit code...
git add -A && git commit -m "..."
git push                          # → origin/main (minimaldigital93)
```

### Deploy (run in `AMS_APP`, on the host Mac)

```bash
git pull && composer deploy       # pull latest, then ship (see below)
```

## TL;DR — quick reference

```bash
# After editing code — ship it (this is the everyday command)
composer deploy

# Pull latest, then ship
git pull && composer deploy

# Take site OFFLINE   (tunnel first, then the backends)
launchctl bootout gui/$(id -u)/com.minimaldigital.cloudflared
launchctl bootout gui/$(id -u)/com.minimaldigital.smartsell
launchctl bootout gui/$(id -u)/com.minimaldigital.laravel
brew services stop nginx

# Bring site ONLINE   (backends first, tunnel LAST)
brew services start nginx
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.smartsell.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist

# Restart just the app after editing .env
launchctl bootout   gui/$(id -u)/com.minimaldigital.laravel
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist

# Status & logs
launchctl list | grep minimaldigital
brew services list | grep nginx
tail -f storage/logs/serve.out.log storage/logs/cloudflared.out.log

# Fresh clone / new machine
composer setup
```

**Rules of thumb:**
- Plain code change → just `composer deploy`. No service restart needed.
- Restart services only when `.env`, a `.plist`, or the tunnel config changes.
- Stopping order: tunnel → backends. Starting order: backends → tunnel.
- Mac must stay **awake & logged in** or the site goes down (`caffeinate -s`).

## Architecture

```
  Visitor's browser
        │
        ▼
   minimaldigital.dev / www    ← domain, DNS on Cloudflare (mycoding5555 account; NS elsa/theo)
        │
        ▼
  Cloudflare's network
        │   (encrypted outbound tunnel — no open ports on the Mac)
        ▼
  cloudflared  (tunnel: minimaldigital, id e3728a94-42ff-470f-a6f7-ef0f46b6388d)
        │
        ├─ path /smart_sell/*  ───────────►  Next.js (next-server) on localhost:3000
        │
        └─ everything else     ───────────►  nginx on 127.0.0.1:8090
                                                 │
                                                 ├─ /         → placeholder "coming soon"
                                                 └─ /ams_app/ → proxy_pass http://127.0.0.1:8000/
                                                                    │
                                                                    ▼
                                                            php artisan serve (Laravel, AMS_APP)
```

### The pieces

1. **`cloudflared`** — holds an outbound tunnel to Cloudflare and routes public
   requests **by path**. Managed by `com.minimaldigital.cloudflared.plist`.
   Ingress rules live in `~/.cloudflared/config.yml`:
   - `minimaldigital.dev` / `www`, path `^/smart_sell(/.*)?$` → `http://localhost:3000`
   - `minimaldigital.dev` / `www`, everything else            → `http://localhost:8090`
   - anything else                                            → `404`
2. **nginx** — the front door on `127.0.0.1:8090`. Serves the "coming soon"
   placeholder at `/`, and reverse-proxies `/ams_app/` → `http://127.0.0.1:8000/`
   (Laravel runs under the public base path `/ams_app`). Managed by Homebrew
   (`brew services`, `~/Library/LaunchAgents/homebrew.mxcl.nginx.plist`).
3. **`php artisan serve`** — Laravel's dev server, bound to `127.0.0.1:8000`
   (local-only, sits behind nginx). Managed by `com.minimaldigital.laravel.plist`.
4. **Next.js** (`next-server`) — the Smart_sell app on `localhost:3000`
   (basePath `/smart_sell`). Managed by `com.minimaldigital.smartsell.plist`.
5. **launchd** — the `.plist` files in `~/Library/LaunchAgents/` (the three
   `com.minimaldigital.*` services plus `homebrew.mxcl.nginx`) have `RunAtLoad`
   (start at login) and `KeepAlive` (restart on crash), so the stack stays up
   on its own.

## Two meanings of "deploy"

### A) Hosting — keeping the site online (launchd + tunnel)

The stack is **four services**: nginx (front door), Laravel (AMS_APP),
Next.js (Smart_sell), and the cloudflared tunnel.

| Goal | Command |
|------|---------|
| Take site **offline** | `launchctl bootout gui/$(id -u)/com.minimaldigital.cloudflared`<br>`launchctl bootout gui/$(id -u)/com.minimaldigital.smartsell`<br>`launchctl bootout gui/$(id -u)/com.minimaldigital.laravel`<br>`brew services stop nginx` |
| Bring site **online** | `brew services start nginx`<br>`launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist`<br>`launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.smartsell.plist`<br>`launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist` |
| Check status | `launchctl list \| grep minimaldigital` and `brew services list \| grep nginx` |
| Watch logs | `tail -f storage/logs/serve.out.log storage/logs/cloudflared.out.log` |

Order matters: when stopping, drop the tunnel **first**, then the backends; when
starting, bring the backends up **first** and the tunnel **last**, so the public
tunnel never opens before nginx / Laravel / Smart_sell can answer.

Restart one service after a config change (e.g. editing `.env`):

```bash
launchctl bootout    gui/$(id -u)/com.minimaldigital.laravel
launchctl bootstrap  gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist
```

If you edit a `.plist` file in this `deploy/` folder, copy it to
`~/Library/LaunchAgents/` before bootstrapping:

```bash
cp deploy/com.minimaldigital.laravel.plist ~/Library/LaunchAgents/
```

### B) Shipping new code (`composer deploy`)

After changing code, update the live site with the release script defined in
`composer.json`:

```bash
composer deploy
```

Which runs, in order:

1. `artisan down` — maintenance mode (visitors see a 503 instead of half-updated code)
2. `artisan migrate --force` — apply new DB migrations
3. `artisan optimize:clear` then re-cache config / routes / views / events
4. `npm run build` — compile CSS/JS with Vite
5. `artisan up` — back online

You normally do **not** restart the launchd services for code changes —
`artisan serve` serves the latest PHP on the next request and `composer deploy`
rebuilds the cache/asset layer. Only restart services when `.env`, the plist
files, or the tunnel config change.

## Common workflows

**Day-to-day after editing code:**

```bash
git pull         # if pulling changes
composer deploy  # migrate + re-cache + build assets, with a maintenance window
```

## Recover hosting after a machine reset

A full wipe deletes the `~/Library/LaunchAgents/` plists, the nginx config, the
Homebrew packages, and `~/.cloudflared/`. The repo keeps copies of the config
files in `deploy/` so you can rebuild the whole stack. **Secrets are not in the
repo** — the cloudflared credentials (`~/.cloudflared/cert.pem` and
`<tunnel-id>.json`) must be restored from your own backup or re-issued with
`cloudflared tunnel login` / `cloudflared tunnel token`.

```bash
# 1. Prerequisites
brew install php composer node nginx cloudflared

# 2. Code — both checkouts
#    AMS_APP (this repo) at ~/AMS_APP, Smart_sell at ~/Smart_sell
cd ~/AMS_APP && composer setup            # deps, .env, app key, migrate, build
cd ~/Smart_sell && npm install && npm run build

# 3. Restore configs from this repo into their live locations
cp ~/AMS_APP/deploy/com.minimaldigital.laravel.plist     ~/Library/LaunchAgents/
cp ~/AMS_APP/deploy/com.minimaldigital.smartsell.plist   ~/Library/LaunchAgents/
cp ~/AMS_APP/deploy/com.minimaldigital.cloudflared.plist ~/Library/LaunchAgents/
cp ~/AMS_APP/deploy/nginx/ams_app.conf      /opt/homebrew/etc/nginx/servers/
cp ~/AMS_APP/deploy/cloudflared/config.yml  ~/.cloudflared/

# 4. Restore the cloudflared secrets (NOT in the repo) into ~/.cloudflared/
#    cert.pem  and  e3728a94-42ff-470f-a6f7-ef0f46b6388d.json

# 5. Bring the stack online (backends first, tunnel last)
brew services start nginx
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.smartsell.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist

# 6. Keep the Mac from sleeping (or the tunnel drops)
caffeinate -s
```

The config files backed up in `deploy/`:

| Live location | Backup in repo |
|---|---|
| `~/Library/LaunchAgents/com.minimaldigital.laravel.plist` | `deploy/com.minimaldigital.laravel.plist` |
| `~/Library/LaunchAgents/com.minimaldigital.smartsell.plist` | `deploy/com.minimaldigital.smartsell.plist` |
| `~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist` | `deploy/com.minimaldigital.cloudflared.plist` |
| `/opt/homebrew/etc/nginx/servers/ams_app.conf` | `deploy/nginx/ams_app.conf` |
| `~/.cloudflared/config.yml` | `deploy/cloudflared/config.yml` |
| `~/.cloudflared/cert.pem` + `<tunnel-id>.json` | **not stored — secrets, back up separately** |

## Notes & gotchas

- **The Mac must stay awake and logged in.** Sleep = tunnel drops = site down.
  Use `caffeinate -s` or disable auto-sleep while hosting.
- `php artisan serve` is a single-threaded dev server — fine for low-traffic /
  demo use, not built for heavy production load. For real production, move to
  nginx + php-fpm.
- `bootout` only stops a service for the current login session; it starts again
  at next login. To stop auto-start permanently, move the `.plist` out of
  `~/Library/LaunchAgents/`.
- **DNS lives in the `mycoding5555@gmail.com` Cloudflare account** (zone
  nameservers `elsa.ns.cloudflare.com` / `theo.ns.cloudflare.com`); Namecheap
  must delegate to those. An older duplicate zone exists in the
  `Minimaldigital93@gmail.com` account (`holly`/`rayden`) — if the registrar
  ever points back there, you get **Cloudflare Error 1000 ("DNS points to
  prohibited IP")**. Fix: set Namecheap NS to elsa/theo, then in the
  mycoding5555 account click **Check nameservers now** so the zone goes Active.
  (The cloudflared `cert.pem` token can read/edit that zone's DNS via the API
  but cannot trigger activation.)

  
## boot hotsing
# 1. nginx — the front door on :8090 (Homebrew-managed)
brew services start nginx

# 2. AMS_APP (Laravel) on :8000
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist

# 3. Smart_sell (Next.js) on :3000
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.smartsell.plist

# 4. Cloudflare tunnel — LAST (so it only opens once the backends are up)
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist
Check everything came up:


launchctl list | grep minimaldigital
brew services list | grep nginx
