#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
DB_USER_HOST="${DB_USER_HOST:-localhost}"

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "Please run as root."
        exit 1
    fi
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

read_env() {
    local key="$1"
    local value=""

    if [ -f "$ENV_FILE" ]; then
        value="$(grep -E "^${key}=" "$ENV_FILE" | tail -n1 | cut -d= -f2- || true)"
    fi

    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

set_env() {
    local key="$1"
    local value="$2"
    local escaped

    escaped="$(printf '%s' "$value" | sed -e 's/[|&]/\\&/g')"

    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
    else
        printf '\n%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

run_as_app() {
    local cmd="$1"

    if command_exists sudo; then
        sudo -u "$APP_USER" bash -lc "$cmd"
    else
        su -s /bin/bash "$APP_USER" -c "$cmd"
    fi
}

sql_escape() {
    printf '%s' "$1" | sed "s/'/''/g"
}

parse_app_host() {
    local url="$1"
    local host

    host="$(printf '%s' "$url" | sed -E 's#^[a-zA-Z]+://([^/:]+).*#\1#')"
    if [ -z "$host" ] || [ "$host" = "$url" ]; then
        host="_"
    fi

    printf '%s' "$host"
}

install_packages_apt() {
    apt-get update
    apt-get install -y software-properties-common curl ca-certificates gnupg lsb-release unzip git openssl

    if ! command_exists php8.4; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update
    fi

    apt-get install -y \
        apache2 \
        certbot \
        python3-certbot-apache \
        mariadb-server \
        redis-server \
        freeradius \
        supervisor \
        php8.4-cli \
        php8.4-fpm \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-curl \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-intl \
        php8.4-gd \
        php8.4-mysql \
        php8.4-readline

    if ! command_exists node; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    fi

    if ! command_exists composer; then
        curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
        php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm -f /tmp/composer-setup.php
    fi

    if [ -x /usr/bin/php8.4 ]; then
        update-alternatives --set php /usr/bin/php8.4 || true
    fi
}

setup_env() {
    if [ ! -f "$ENV_FILE" ]; then
        cp "$APP_DIR/.env.example" "$ENV_FILE"
    fi

    local app_url="${APP_URL:-$(read_env APP_URL)}"
    local app_env="${APP_ENV:-$(read_env APP_ENV)}"
    local app_debug="${APP_DEBUG:-$(read_env APP_DEBUG)}"
    local db_database="${DB_DATABASE:-$(read_env DB_DATABASE)}"
    local db_username="${DB_USERNAME:-$(read_env DB_USERNAME)}"
    local db_password="${DB_PASSWORD:-$(read_env DB_PASSWORD)}"
    local db_host="${DB_HOST:-$(read_env DB_HOST)}"
    local radius_server_ip="${RADIUS_SERVER_IP:-$(read_env RADIUS_SERVER_IP)}"

    if [ -z "$app_url" ]; then
        app_url="http://localhost"
    fi

    if [ -z "$app_env" ]; then
        app_env="production"
    fi

    if [ -z "$app_debug" ]; then
        app_debug="false"
    fi

    if [ -z "$db_database" ]; then
        db_database="rafen"
    fi

    if [ -z "$db_username" ]; then
        db_username="rafen"
    fi

    if [ -z "$db_password" ]; then
        db_password="$(openssl rand -base64 18)"
    fi

    if [ -z "$db_host" ]; then
        db_host="127.0.0.1"
    fi

    if [ -z "$radius_server_ip" ]; then
        radius_server_ip="127.0.0.1"
    fi

    set_env APP_URL "$app_url"
    set_env APP_ENV "$app_env"
    set_env APP_DEBUG "$app_debug"
    set_env DB_DATABASE "$db_database"
    set_env DB_USERNAME "$db_username"
    set_env DB_PASSWORD "$db_password"
    set_env DB_HOST "$db_host"
    set_env RADIUS_CLIENTS_PATH "/etc/freeradius/clients.d/laravel.conf"
    set_env RADIUS_RELOAD_COMMAND "sudo systemctl reload freeradius"
    set_env RADIUS_RESTART_COMMAND "sudo systemctl restart freeradius"
    set_env RADIUS_SERVER_IP "$radius_server_ip"
}

setup_database() {
    local db_database
    local db_username
    local db_password
    local db_database_sql
    local db_username_sql
    local db_password_sql
    local db_user_host_sql

    db_database="$(read_env DB_DATABASE)"
    db_username="$(read_env DB_USERNAME)"
    db_password="$(read_env DB_PASSWORD)"
    db_database_sql="$(sql_escape "$db_database")"
    db_username_sql="$(sql_escape "$db_username")"
    db_password_sql="$(sql_escape "$db_password")"
    db_user_host_sql="$(sql_escape "$DB_USER_HOST")"

    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${db_database_sql}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_username_sql}'@'${db_user_host_sql}' IDENTIFIED BY '${db_password_sql}';
GRANT ALL PRIVILEGES ON \`${db_database_sql}\`.* TO '${db_username_sql}'@'${db_user_host_sql}';
FLUSH PRIVILEGES;
SQL
}

setup_freeradius() {
    local clients_path

    clients_path="$(read_env RADIUS_CLIENTS_PATH)"
    install -d -m 0755 "$(dirname "$clients_path")"
    if [ ! -f "$clients_path" ]; then
        touch "$clients_path"
    fi

    if getent group freerad >/dev/null 2>&1; then
        chown "$APP_USER":freerad "$clients_path"
        chmod 0640 "$clients_path"
    else
        chown "$APP_USER":"$APP_GROUP" "$clients_path"
        chmod 0644 "$clients_path"
    fi

    cat >/etc/sudoers.d/rafen-freeradius <<'EOF'
Defaults:www-data !requiretty
www-data ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius
EOF
    chmod 0440 /etc/sudoers.d/rafen-freeradius
}

check_permissions() {
    local missing=0
    local app_user_uid
    local app_group_gid
    local clients_path
    local env_path

    app_user_uid="$(id -u "$APP_USER" 2>/dev/null || true)"
    app_group_gid="$(getent group "$APP_GROUP" | cut -d: -f3 || true)"
    clients_path="$(read_env RADIUS_CLIENTS_PATH)"
    env_path="$ENV_FILE"

    if [ -z "$app_user_uid" ]; then
        echo "NOTIFIKASI: user ${APP_USER} belum ada. Buat user atau set APP_USER."
        missing=1
    fi

    if [ -z "$app_group_gid" ]; then
        echo "NOTIFIKASI: group ${APP_GROUP} belum ada. Buat group atau set APP_GROUP."
        missing=1
    fi

    if [ -n "$app_user_uid" ] && [ ! -w "$APP_DIR/storage" ]; then
        echo "NOTIFIKASI: ${APP_DIR}/storage belum writable untuk ${APP_USER}. Set ownership dan permission."
        missing=1
    fi

    if [ -n "$app_user_uid" ] && [ ! -w "$APP_DIR/bootstrap/cache" ]; then
        echo "NOTIFIKASI: ${APP_DIR}/bootstrap/cache belum writable untuk ${APP_USER}. Set ownership dan permission."
        missing=1
    fi

    if [ -n "$app_user_uid" ] && [ ! -w "$env_path" ]; then
        echo "NOTIFIKASI: ${env_path} belum writable untuk ${APP_USER}."
        missing=1
    fi

    if [ -n "$clients_path" ] && [ ! -w "$clients_path" ]; then
        echo "NOTIFIKASI: ${clients_path} belum writable untuk ${APP_USER} agar sync RADIUS berjalan."
        missing=1
    fi

    if [ ! -f /etc/sudoers.d/rafen-freeradius ]; then
        echo "NOTIFIKASI: sudoers FreeRADIUS belum ada (/etc/sudoers.d/rafen-freeradius)."
        missing=1
    fi

    if [ "$missing" -eq 0 ]; then
        echo "Semua akses penting sudah diset."
    fi
}

prompt_domain() {
    local value

    if [ -n "${VHOST_DOMAIN:-}" ]; then
        VHOST_DOMAIN="${VHOST_DOMAIN}"
        return
    fi

    read -r -p "Masukkan domain vhost (kosongkan jika skip Apache/SSL): " value
    VHOST_DOMAIN="$value"
}

prompt_certbot_email() {
    local value

    if [ -n "${CERTBOT_EMAIL:-}" ]; then
        CERTBOT_EMAIL="${CERTBOT_EMAIL}"
        return
    fi

    read -r -p "Email untuk SSL (Let's Encrypt): " value
    if [ -z "$value" ] && [ -n "$VHOST_DOMAIN" ]; then
        value="admin@${VHOST_DOMAIN}"
    fi
    CERTBOT_EMAIL="$value"
}

setup_apache() {
    local domain

    domain="$VHOST_DOMAIN"
    if [ -z "$domain" ]; then
        return
    fi

    a2enmod rewrite ssl >/dev/null

    cat >/etc/apache2/sites-available/rafen.conf <<EOF
<VirtualHost *:80>
    ServerName ${domain}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/rafen_error.log
    CustomLog \${APACHE_LOG_DIR}/rafen_access.log combined
</VirtualHost>
EOF

    a2ensite rafen.conf >/dev/null
    systemctl reload apache2

    if [ -n "$CERTBOT_EMAIL" ]; then
        certbot --apache -d "$domain" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect
    else
        echo "NOTIFIKASI: Email SSL kosong, lewati auto SSL."
    fi
}

setup_systemd_services() {
    cat >/etc/systemd/system/rafen-queue.service <<EOF
[Unit]
Description=Rafen queue worker
After=network.target mariadb.service

[Service]
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

    cat >/etc/systemd/system/rafen-schedule.service <<EOF
[Unit]
Description=Rafen scheduler

[Service]
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan schedule:run
EOF

    cat >/etc/systemd/system/rafen-schedule.timer <<'EOF'
[Unit]
Description=Rafen scheduler timer

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable --now rafen-queue.service
    systemctl enable --now rafen-schedule.timer
}

setup_app() {
    chown -R "$APP_USER":"$APP_GROUP" "$APP_DIR"
    chown -R "$APP_USER":"$APP_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 0775 {} +

    local app_key
    app_key="$(read_env APP_KEY)"

    run_as_app "cd \"$APP_DIR\" && composer install --no-dev --optimize-autoloader"

    if [ -z "$app_key" ]; then
        run_as_app "cd \"$APP_DIR\" && php artisan key:generate --force"
    fi

    run_as_app "cd \"$APP_DIR\" && php artisan migrate --force"
    run_as_app "cd \"$APP_DIR\" && php artisan storage:link"
    run_as_app "cd \"$APP_DIR\" && php artisan config:cache"
    run_as_app "cd \"$APP_DIR\" && php artisan route:cache"
    run_as_app "cd \"$APP_DIR\" && php artisan view:cache"

    run_as_app "cd \"$APP_DIR\" && npm install"
    run_as_app "cd \"$APP_DIR\" && npm run build"
}

enable_services() {
    systemctl enable --now php8.4-fpm
    systemctl enable --now mariadb
    systemctl enable --now redis-server
    systemctl enable --now freeradius
    systemctl enable --now apache2
}

main() {
    require_root

    if command_exists apt-get; then
        install_packages_apt
    else
        echo "Unsupported OS. Please use a Debian/Ubuntu-based system."
        exit 1
    fi

    setup_env
    prompt_domain
    if [ -n "$VHOST_DOMAIN" ]; then
        prompt_certbot_email
    fi
    enable_services
    setup_database
    setup_freeradius
    setup_apache
    setup_systemd_services
    setup_app
    check_permissions

    echo "Installation complete."
    echo "APP_URL=$(read_env APP_URL)"
}

main "$@"
