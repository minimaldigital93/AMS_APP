# AMS_APP — Customer Data Backup Guide

How to back up (and restore) all customer data to an external hard drive.

---

## 1. What counts as "customer data"

Customer data in AMS_APP lives in **two** places. A real backup must capture **both**:

| What | Where | Notes |
|------|-------|-------|
| **Database** | MySQL database `AMS_APP` (127.0.0.1:3306) | 42 tables — customers, tenants, expenses, payments, etc. This is the bulk of it. |
| **Uploaded files** | `storage/app/` (`business_expenses/`, `tenants/`) | Receipts/images uploaded by users (~5 MB). |
| **Secrets (for restore)** | `.env` | Not "customer data", but you need its credentials to restore. Keep it private. |

> The database alone is **not** a full backup — without `storage/app` your uploaded receipts/photos are gone.

---

## 2. Prerequisites (one-time check)

- **`mysqldump` installed** — yes, at `/opt/homebrew/bin/mysqldump`. Verify:
  ```bash
  which mysqldump
  ```
- **MySQL running** and the app's `.env` has valid DB credentials (it does — verified: 42 tables reachable).
- **An external HDD**, plugged in and mounted under `/Volumes`.

> 🔒 **Recommended:** use an **encrypted** external drive. The backup includes `.env` (DB password + app secrets) and personal customer data. In **Disk Utility** you can erase the drive as **APFS (Encrypted)** or right-click the drive in Finder → **Encrypt**.

---

## 3. The easy way — run the script

A ready-made script lives at [deploy/backup.sh](backup.sh). It does all three parts (database + files + `.env`) into one timestamped folder.

### Steps

1. **Plug in** your external HDD.

2. **Find its name:**
   ```bash
   ls /Volumes
   ```
   Example output: `Macintosh HD`, `MyBackup` → your drive is `MyBackup`.

3. **Run the backup** (from the app folder):
   ```bash
   cd ~/AMS_APP
   deploy/backup.sh "/Volumes/MyBackup"
   ```

4. **Wait for** `==> Backup complete` and the file listing.

5. **Eject safely** in Finder (the script already ran `sync`, so it's safe), then unplug.

### What you get

```
/Volumes/MyBackup/AMS_APP_backup/20260622-101530/
├── db-AMS_APP-20260622-101530.sql.gz    ← whole database (gzipped)
├── storage-app-20260622-101530.tar.gz   ← uploaded files
└── .env.backup                          ← credentials for restore
```

Each run creates a **new timestamped folder** — older backups are kept, nothing is overwritten.

> ✅ The script uses `--single-transaction`, so it's safe to run **while the live app is serving traffic** — no downtime, consistent snapshot.

---

## 4. The manual way — if you prefer running commands yourself

Do this from `~/AMS_APP`. Replace `/Volumes/MyBackup` with your drive.

```bash
cd ~/AMS_APP

# Pick a destination folder with today's date/time
DEST="/Volumes/MyBackup/AMS_APP_backup/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DEST"

# 1) Database dump (you'll be prompted for the MySQL root password)
mysqldump -u root -p --single-transaction --quick --routines --triggers \
  AMS_APP | gzip > "$DEST/db-AMS_APP.sql.gz"

# 2) Uploaded files
tar -czf "$DEST/storage-app.tar.gz" -C ~/AMS_APP storage/app

# 3) Secrets (needed to restore)
cp .env "$DEST/.env.backup"

# 4) Flush to disk so it's safe to unplug
sync
ls -lh "$DEST"
```

---

## 5. Verify a backup is good

A backup you've never tested is not a backup. Quick checks:

```bash
cd /Volumes/MyBackup/AMS_APP_backup/<timestamp>

# Database dump isn't empty and unzips cleanly
gunzip -t db-AMS_APP-*.sql.gz && echo "DB dump OK"
gunzip < db-AMS_APP-*.sql.gz | grep -c "CREATE TABLE"   # expect ~42

# File archive lists its contents
tar -tzf storage-app-*.tar.gz | head
```

---

## 6. Restore (when you need to recover)

> ⚠️ Restoring **overwrites** current data. Only do this on a fresh/empty target or when you intend to replace what's there.

```bash
cd /Volumes/MyBackup/AMS_APP_backup/<timestamp>

# 1) Restore the database into AMS_APP
gunzip < db-AMS_APP-*.sql.gz | mysql -u root -p AMS_APP

# 2) Restore uploaded files (run so it lands in ~/AMS_APP/storage/app)
tar -xzf storage-app-*.tar.gz -C ~/AMS_APP

# 3) If this is a brand-new machine, also restore the env file
#    cp .env.backup ~/AMS_APP/.env   # review first; don't clobber a good .env
```

If the database doesn't exist yet (new machine):
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS AMS_APP CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 7. How often / how many to keep

- **How often:** before any deploy or risky change, and on a regular cadence (e.g. daily/weekly) depending on how much new customer data you can afford to lose.
- **Retention:** each backup is small (a few MB). Delete old timestamped folders occasionally so the drive doesn't fill up:
  ```bash
  ls -dt /Volumes/MyBackup/AMS_APP_backup/*/        # newest first
  # rm -rf the old ones you no longer need
  ```
- **3-2-1 rule (ideal):** keep **3** copies, on **2** kinds of media, with **1** off-site (e.g. this HDD + an occasional copy to cloud storage).

---

## 8. Automating it (optional)

Because the drive is local, the right tool is a macOS **`launchd`** job (same mechanism as
[com.minimaldigital.smartsell.plist](com.minimaldigital.smartsell.plist)) that runs
`deploy/backup.sh` on a schedule or when the drive mounts.

> Note: Claude Code's `/schedule` and `/loop` run **cloud** agents and cannot touch a physical HDD — they're not suitable here. Use `launchd` instead. Ask if you want the plist written.

---

## Quick reference

```bash
# Back up
cd ~/AMS_APP && deploy/backup.sh "/Volumes/<YourDrive>"

# Restore
cd /Volumes/<YourDrive>/AMS_APP_backup/<timestamp>
gunzip < db-AMS_APP-*.sql.gz | mysql -u root -p AMS_APP
tar -xzf storage-app-*.tar.gz -C ~/AMS_APP
```
