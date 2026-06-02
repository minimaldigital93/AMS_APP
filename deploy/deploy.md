# Deploy & Hosting Runbook — AMS_APP

This app is **self-hosted on this Mac**. There is no cloud server: the Mac runs
the Laravel app, and a Cloudflare Tunnel pipes public traffic from
**minimaldigital.dev** down to it.

## TL;DR — quick reference

```bash
# After editing code — ship it (this is the everyday command)
composer deploy

# Pull latest, then ship
git pull && composer deploy

# Take site OFFLINE   (tunnel first, then server)
launchctl bootout gui/$(id -u)/com.minimaldigital.cloudflared
launchctl bootout gui/$(id -u)/com.minimaldigital.laravel

# Bring site ONLINE   (server first, then tunnel)
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist

# Restart just the app after editing .env
launchctl bootout   gui/$(id -u)/com.minimaldigital.laravel
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist

# Status & logs
launchctl list | grep minimaldigital
tail -f storage/logs/serve.out.log storage/logs/cloudflared.out.log

# Fresh clone / new machine
composer setup
```

**Rules of thumb:**
- Plain code change → just `composer deploy`. No service restart needed.
- Restart services only when `.env`, a `.plist`, or the tunnel config changes.
- Stopping order: tunnel → server. Starting order: server → tunnel.
- Mac must stay **awake & logged in** or the site goes down (`caffeinate -s`).

## Architecture

```
  Visitor's browser
        │
        ▼
   minimaldigital.dev          ← domain, DNS managed by Cloudflare
        │
        ▼
  Cloudflare's network
        │   (encrypted outbound tunnel — no open ports on the Mac)
        ▼
  cloudflared  (tunnel: minimaldigital, id e3728a94-42ff-470f-a6f7-ef0f46b6388d)
        │   forwards to http://localhost:8000
        ▼
  php artisan serve  on 127.0.0.1:8000
        │
        ▼
   Laravel app (AMS_APP)
```

### The three pieces

1. **`php artisan serve`** — Laravel's dev server, bound to `127.0.0.1:8000`
   (local-only). Managed by `com.minimaldigital.laravel.plist`.
2. **`cloudflared`** — holds an outbound tunnel to Cloudflare and forwards
   public requests to `localhost:8000`. Managed by
   `com.minimaldigital.cloudflared.plist`. Ingress rules live in
   `~/.cloudflared/config.yml`:
   - `minimaldigital.dev`      → `http://localhost:8000`
   - `www.minimaldigital.dev`  → `http://localhost:8000`
   - anything else             → `404`
3. **launchd** — the two `.plist` files in `~/Library/LaunchAgents/` are macOS
   services with `RunAtLoad` (start at login) and `KeepAlive` (restart on
   crash), so the site stays up on its own.

## Two meanings of "deploy"

### A) Hosting — keeping the site online (launchd + tunnel)

| Goal | Command |
|------|---------|
| Take site **offline** | `launchctl bootout gui/$(id -u)/com.minimaldigital.cloudflared`<br>`launchctl bootout gui/$(id -u)/com.minimaldigital.laravel` |
| Bring site **online** | `launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.laravel.plist`<br>`launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.minimaldigital.cloudflared.plist` |
| Check status | `launchctl list \| grep minimaldigital` |
| Watch logs | `tail -f storage/logs/serve.out.log storage/logs/cloudflared.out.log` |

Order matters when stopping: drop the tunnel first, then the server.

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

**Fresh machine / fresh clone:**

```bash
composer setup   # deps, .env, app key, migrate, build assets
```

**Day-to-day after editing code:**

```bash
git pull         # if pulling changes
composer deploy  # migrate + re-cache + build assets, with a maintenance window
```

## Notes & gotchas

- **The Mac must stay awake and logged in.** Sleep = tunnel drops = site down.
  Use `caffeinate -s` or disable auto-sleep while hosting.
- `php artisan serve` is a single-threaded dev server — fine for low-traffic /
  demo use, not built for heavy production load. For real production, move to
  nginx + php-fpm.
- `bootout` only stops a service for the current login session; it starts again
  at next login. To stop auto-start permanently, move the `.plist` out of
  `~/Library/LaunchAgents/`.
