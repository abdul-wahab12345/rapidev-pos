# GitHub Actions — Required Secrets

Go to your repository → **Settings → Secrets and variables → Actions → New repository secret**
and add each secret below.

---

## Required secrets

| Secret | Description | Example |
|---|---|---|
| `FTP_SERVER` | FTP server hostname or IP | `ftp.myrestaurant.com` |
| `FTP_USERNAME` | FTP username | `user@myrestaurant.com` |
| `FTP_PASSWORD` | FTP password | `your_ftp_password` |
| `FTP_PORT` | FTP port (default 21) | `21` |
| `FTP_PROTOCOL` | Protocol: `ftp` or `ftps` | `ftps` |
| `FTP_SERVER_DIR` | Remote path to your app root | `/public_html/` or `/www/pos/` |

---

## What gets deployed automatically (on push to `resturant` branch)

| Included | Excluded |
|---|---|
| All PHP source files | `.env` (never uploaded) |
| `public/build/` (compiled JS/CSS) | `node_modules/` |
| `resources/`, `routes/`, `app/`, `config/` | `vendor/` |
| `database/migrations/` | `storage/logs/`, `storage/framework/` |
| `composer.json`, `composer.lock` | `tests/`, `scripts/` |

---

## After each deployment — run manually on server

SSH or use your hosting control panel (File Manager terminal / SSH) to run:

```bash
cd /path/to/your/app

# 1. Install / update PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Run any new database migrations
php artisan migrate --force

# 3. Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart queue workers (if you use them)
php artisan queue:restart
```

> **Tip:** Save these commands in a single `post-deploy.sh` file on your server
> so you only need to run one command: `bash post-deploy.sh`

---

## One-time server setup (first deploy only)

```bash
# Copy .env.example and fill in your values
cp .env.example .env
nano .env          # set APP_KEY, DB_*, APP_URL

# Generate app key
php artisan key:generate

# Create storage symlink
php artisan storage:link

# Run first migration
php artisan migrate --force
```

---

## Deployment flow summary

```
You push to `resturant` branch
        │
        ▼
GitHub Actions runner
  ├── npm ci
  ├── npm run build         ← compiles JS/CSS into public/build/
  └── FTP upload            ← sends all files to server (vendor/ excluded)
        │
        ▼
Your server (manual steps)
  ├── composer install      ← installs PHP dependencies
  ├── php artisan migrate   ← runs new migrations
  └── php artisan optimize  ← refreshes caches
```
