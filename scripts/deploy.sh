#!/usr/bin/env bash
# =============================================================================
#  RapiDev POS — Manual deployment script
#  Run this directly on the server when you want to deploy without GitHub Actions.
#
#  Usage:
#    bash scripts/deploy.sh
#    APP_DIR=/var/www/rapidev-pos bash scripts/deploy.sh
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/rapidev-pos}"
SHARED_DIR="$APP_DIR/shared"
RELEASES_DIR="$APP_DIR/releases"
CURRENT_LINK="$APP_DIR/current"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"
KEEP_RELEASES=5

# ── Colours ───────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; CYAN='\033[0;36m'; NC='\033[0m'
step()    { echo -e "\n${CYAN}▶ $*${NC}"; }
info()    { echo -e "  ${GREEN}✓${NC} $*"; }
warning() { echo -e "  ${YELLOW}!${NC} $*"; }
error()   { echo -e "  ${RED}✗ ERROR:${NC} $*"; exit 1; }

echo -e "${GREEN}"
echo "  ██████╗  █████╗ ██████╗ ██╗██████╗ ███████╗██╗   ██╗"
echo "  ██╔══██╗██╔══██╗██╔══██╗██║██╔══██╗██╔════╝██║   ██║"
echo "  ██████╔╝███████║██████╔╝██║██║  ██║█████╗  ██║   ██║"
echo "  ██╔══██╗██╔══██║██╔═══╝ ██║██║  ██║██╔══╝  ╚██╗ ██╔╝"
echo "  ██║  ██║██║  ██║██║     ██║██████╔╝███████╗ ╚████╔╝ "
echo "  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝╚═════╝ ╚══════╝  ╚═══╝  POS"
echo -e "${NC}"
echo -e "  Deploying at: ${YELLOW}$TIMESTAMP${NC}"
echo -e "  Target dir:   ${YELLOW}$APP_DIR${NC}"
echo ""

# ── Guards ────────────────────────────────────────────────────────────────────
[[ -d "$APP_DIR" ]]           || error "APP_DIR not found: $APP_DIR  (run setup-server.sh first)"
[[ -f "$SHARED_DIR/.env" ]]   || error "Shared .env not found at $SHARED_DIR/.env"
command -v php   &>/dev/null  || error "PHP not found on PATH"
command -v composer &>/dev/null || error "Composer not found on PATH"

# ── 1. Get source code ────────────────────────────────────────────────────────
step "Cloning / pulling latest code..."
if [[ -d "$APP_DIR/repo/.git" ]]; then
    cd "$APP_DIR/repo"
    git fetch --all
    git reset --hard origin/main
    info "Pulled latest from origin/main"
else
    mkdir -p "$APP_DIR/repo"
    git clone "$(grep REPO_URL "$SHARED_DIR/.env" | cut -d '=' -f2 || echo '')" "$APP_DIR/repo" \
        || error "Could not clone repo — set REPO_URL in $SHARED_DIR/.env or clone manually into $APP_DIR/repo"
    cd "$APP_DIR/repo"
fi

# ── 2. Create release snapshot ────────────────────────────────────────────────
step "Creating release snapshot: $RELEASE_DIR"
mkdir -p "$RELEASE_DIR"
rsync -a \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='storage/' \
    --exclude='.env' \
    "$APP_DIR/repo/" "$RELEASE_DIR/"
info "Snapshot copied"

# ── 3. Link shared resources ──────────────────────────────────────────────────
step "Linking shared .env and storage..."
ln -sfn "$SHARED_DIR/.env"     "$RELEASE_DIR/.env"
ln -sfn "$SHARED_DIR/storage"  "$RELEASE_DIR/storage"
info "Symlinks created"

# ── 4. PHP dependencies ───────────────────────────────────────────────────────
step "Installing Composer dependencies..."
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist -q
info "Composer done"

# ── 5. Frontend assets ────────────────────────────────────────────────────────
step "Building frontend assets..."
if command -v npm &>/dev/null; then
    cd "$APP_DIR/repo"
    npm ci --silent
    npm run build --silent
    # Copy built assets into the release
    cp -r "$APP_DIR/repo/public/build" "$RELEASE_DIR/public/build"
    info "Vite build done"
else
    # If Node is not on this server, check if public/build was included in the release archive
    if [[ -d "$RELEASE_DIR/public/build" ]]; then
        info "Using pre-built assets from archive"
    else
        warning "npm not found and no public/build in release — frontend assets may be missing!"
    fi
fi

# ── 6. Maintenance mode ───────────────────────────────────────────────────────
step "Enabling maintenance mode..."
if [[ -L "$CURRENT_LINK" ]]; then
    php "$CURRENT_LINK/artisan" down --retry=5 --secret="rapidev-bypass" 2>/dev/null || true
fi

# ── 7. Database migrations ────────────────────────────────────────────────────
step "Running database migrations..."
cd "$RELEASE_DIR"
php artisan migrate --force
info "Migrations done"

# ── 8. Optimise ───────────────────────────────────────────────────────────────
step "Caching config, routes, views, events..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
info "Caches written"

# ── 9. Storage link ───────────────────────────────────────────────────────────
step "Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

# ── 10. Permissions ───────────────────────────────────────────────────────────
step "Setting file permissions..."
chmod -R 775 "$SHARED_DIR/storage"
chmod -R 755 "$RELEASE_DIR/bootstrap/cache"

# ── 11. Activate release ──────────────────────────────────────────────────────
step "Switching to new release..."
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"
info "Current → $RELEASE_DIR"

# ── 12. Disable maintenance mode ─────────────────────────────────────────────
step "Disabling maintenance mode..."
php "$CURRENT_LINK/artisan" up
info "App is live"

# ── 13. Restart services ─────────────────────────────────────────────────────
step "Restarting services..."
sudo systemctl reload php8.2-fpm 2>/dev/null || sudo service php8.2-fpm reload 2>/dev/null || true
php "$CURRENT_LINK/artisan" queue:restart 2>/dev/null || true
sudo supervisorctl restart rapidev-pos-worker:* 2>/dev/null || true
info "PHP-FPM reloaded, queue workers restarted"

# ── 14. Cleanup old releases ──────────────────────────────────────────────────
step "Cleaning up old releases (keeping last $KEEP_RELEASES)..."
DELETED=$(ls -dt "$RELEASES_DIR"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)))
if [[ -n "$DELETED" ]]; then
    echo "$DELETED" | xargs rm -rf
    info "Removed: $(echo "$DELETED" | wc -l | tr -d ' ') old release(s)"
else
    info "Nothing to clean"
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}======================================================"
echo -e "  ✅  Deployment complete!"
echo -e "  Release: $TIMESTAMP"
echo -e "======================================================${NC}"
echo ""
