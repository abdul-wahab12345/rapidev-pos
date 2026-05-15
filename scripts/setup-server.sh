#!/usr/bin/env bash
# =============================================================================
#  RapiDev POS — One-time server setup script
#  Run this ONCE on a fresh Ubuntu 22.04 / 24.04 VPS before the first deploy.
#
#  Usage:
#    chmod +x scripts/setup-server.sh
#    sudo bash scripts/setup-server.sh
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/rapidev-pos}"
APP_USER="${APP_USER:-www-data}"
DOMAIN="${DOMAIN:-your-domain.com}"
PHP_VERSION="8.2"

# ── Colours ───────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}[INFO]${NC} $*"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERR] ${NC} $*"; exit 1; }

[[ $EUID -ne 0 ]] && error "Run this script as root: sudo bash setup-server.sh"

# ── 1. System packages ────────────────────────────────────────────────────────
info "Updating system packages..."
apt-get update -q
apt-get install -yq curl wget git unzip software-properties-common gnupg2 ufw

# ── 2. PHP 8.2 ───────────────────────────────────────────────────────────────
info "Installing PHP ${PHP_VERSION}..."
add-apt-repository ppa:ondrej/php -y
apt-get update -q
apt-get install -yq \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-tokenizer \
    php${PHP_VERSION}-ctype \
    php${PHP_VERSION}-json

# ── 3. Composer ───────────────────────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
    info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# ── 4. MySQL ──────────────────────────────────────────────────────────────────
info "Installing MySQL..."
apt-get install -yq mysql-server
systemctl enable --now mysql

info "Securing MySQL..."
mysql -e "CREATE DATABASE IF NOT EXISTS rapidev_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'rapidev'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';"
mysql -e "GRANT ALL PRIVILEGES ON rapidev_pos.* TO 'rapidev'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
warning "MySQL user 'rapidev' created with password 'CHANGE_THIS_PASSWORD' — change it immediately!"

# ── 5. Nginx ──────────────────────────────────────────────────────────────────
info "Installing Nginx..."
apt-get install -yq nginx
systemctl enable nginx

info "Writing Nginx config for ${DOMAIN}..."
cat > /etc/nginx/sites-available/rapidev-pos << NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${APP_DIR}/current/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Logging
    access_log /var/log/nginx/rapidev-pos.access.log;
    error_log  /var/log/nginx/rapidev-pos.error.log;

    # Max upload size (for logo/images)
    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Block access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sfn /etc/nginx/sites-available/rapidev-pos /etc/nginx/sites-enabled/rapidev-pos
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── 6. Supervisor (queue workers) ────────────────────────────────────────────
info "Installing Supervisor..."
apt-get install -yq supervisor
systemctl enable supervisor

cat > /etc/supervisor/conf.d/rapidev-pos-worker.conf << SUPERVISOR
[program:rapidev-pos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${APP_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=${APP_DIR}/shared/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR

supervisorctl reread
supervisorctl update

# ── 7. Directory structure ────────────────────────────────────────────────────
info "Creating deployment directory structure..."
mkdir -p "${APP_DIR}/releases"
mkdir -p "${APP_DIR}/shared/storage/app/public"
mkdir -p "${APP_DIR}/shared/storage/framework/cache/data"
mkdir -p "${APP_DIR}/shared/storage/framework/sessions"
mkdir -p "${APP_DIR}/shared/storage/framework/views"
mkdir -p "${APP_DIR}/shared/storage/logs"

chown -R "${APP_USER}:${APP_USER}" "${APP_DIR}"
chmod -R 775 "${APP_DIR}/shared/storage"

# ── 8. .env file placeholder ──────────────────────────────────────────────────
if [[ ! -f "${APP_DIR}/shared/.env" ]]; then
    info "Creating shared .env placeholder — fill in your values!"
    cat > "${APP_DIR}/shared/.env" << 'ENV'
APP_NAME="Rapidev POS"
APP_ENV=production
APP_KEY=                         # run: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rapidev_pos
DB_USERNAME=rapidev
DB_PASSWORD=CHANGE_THIS_PASSWORD

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="Rapidev POS"
ENV
    warning "Edit ${APP_DIR}/shared/.env before deploying!"
fi

# ── 9. Firewall ───────────────────────────────────────────────────────────────
info "Configuring firewall..."
ufw --force enable
ufw allow OpenSSH
ufw allow 'Nginx Full'

# ── 10. SSH deploy key hint ───────────────────────────────────────────────────
info "Generating deploy SSH key (add the public key to GitHub as a Deploy Key)..."
if [[ ! -f /root/.ssh/deploy_rsa ]]; then
    ssh-keygen -t rsa -b 4096 -C "deploy@${DOMAIN}" -f /root/.ssh/deploy_rsa -N ""
fi

echo ""
echo "========================================================"
echo "  Server setup complete!"
echo "========================================================"
echo ""
echo "  Next steps:"
echo "  1. Edit:  ${APP_DIR}/shared/.env"
echo "  2. Set GitHub Actions secrets (see scripts/DEPLOY_SECRETS.md)"
echo "  3. Push to main branch to trigger first deployment"
echo ""
echo "  Public deploy key (add to GitHub → Settings → Deploy keys):"
cat /root/.ssh/deploy_rsa.pub
echo ""
