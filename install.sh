#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
DB_USER_HOST="${DB_USER_HOST:-localhost}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
FORCE_SYSTEMD="${FORCE_SYSTEMD:-0}"
DEPLOY_DB_PASSWORD="${DEPLOY_DB_PASSWORD:-}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "Please run as root."
        exit 1
    fi
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

run_mysql_root() {
    local sql="$1"

    if [ -n "$MYSQL_ROOT_PASSWORD" ]; then
        mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "$sql"
    else
        mysql -u root -e "$sql"
    fi
}

service_unit_exists() {
    local unit="$1"

    if systemctl list-unit-files --type=service --no-legend | awk '{print $1}' | grep -Fxq "$unit"; then
        return 0
    fi

    return 1
}

timer_unit_exists() {
    local unit="$1"

    if systemctl list-unit-files --type=timer --no-legend | awk '{print $1}' | grep -Fxq "$unit"; then
        return 0
    fi

    return 1
}

enable_service_if_present() {
    local unit="$1"

    if service_unit_exists "$unit"; then
        if systemctl is-enabled --quiet "$unit"; then
            echo "Service ${unit} sudah enabled, skip."
        else
            systemctl enable --now "$unit"
        fi
    else
        echo "NOTIFIKASI: service ${unit} tidak ditemukan, skip."
    fi
}

enable_timer_if_present() {
    local unit="$1"

    if timer_unit_exists "$unit"; then
        if systemctl is-enabled --quiet "$unit"; then
            echo "Timer ${unit} sudah enabled, skip."
        else
            systemctl enable --now "$unit"
        fi
    else
        echo "NOTIFIKASI: timer ${unit} tidak ditemukan, skip."
    fi
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
    local formatted

    escaped="$(printf '%s' "$value" | sed -e 's/"/\\"/g' -e 's/[|&]/\\&/g')"
    if printf '%s' "$value" | grep -q '[[:space:]]'; then
        formatted="\"${escaped}\""
    else
        formatted="${escaped}"
    fi

    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${formatted}|" "$ENV_FILE"
    else
        printf '\n%s=%s\n' "$key" "$formatted" >> "$ENV_FILE"
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
    apt-get install -y software-properties-common curl ca-certificates gnupg lsb-release unzip git openssl debconf-utils

    if ! command_exists php8.4; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update
    fi

    if command_exists debconf-set-selections; then
        echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections
        echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections
    fi

    apt-get install -y \
        apache2 \
        certbot \
        python3-certbot-apache \
        phpmyadmin \
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
    local deploy_db_password
    local deploy_db_password_sql

    db_database="$(read_env DB_DATABASE)"
    db_username="$(read_env DB_USERNAME)"
    db_password="$(read_env DB_PASSWORD)"
    db_database_sql="$(sql_escape "$db_database")"
    db_username_sql="$(sql_escape "$db_username")"
    db_password_sql="$(sql_escape "$db_password")"
    db_user_host_sql="$(sql_escape "$DB_USER_HOST")"

    deploy_db_password="$DEPLOY_DB_PASSWORD"
    if [ -z "$deploy_db_password" ]; then
        deploy_db_password="$DEPLOY_PASSWORD"
    fi
    deploy_db_password_sql="$(sql_escape "$deploy_db_password")"

    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${db_database_sql}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_username_sql}'@'${db_user_host_sql}' IDENTIFIED BY '${db_password_sql}';
GRANT ALL PRIVILEGES ON \`${db_database_sql}\`.* TO '${db_username_sql}'@'${db_user_host_sql}';
FLUSH PRIVILEGES;
SQL

    if [ -n "$deploy_db_password" ]; then
        run_mysql_root "CREATE USER IF NOT EXISTS 'deploy'@'localhost' IDENTIFIED BY '${deploy_db_password_sql}'; GRANT ALL PRIVILEGES ON *.* TO 'deploy'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;"
    else
        echo "NOTIFIKASI: DEPLOY_DB_PASSWORD kosong, user MySQL deploy tidak dibuat."
    fi
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

    if [ ! -f /etc/sudoers.d/rafen-freeradius ]; then
        cat >/etc/sudoers.d/rafen-freeradius <<'EOF'
Defaults:www-data !requiretty
www-data ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius
EOF
        chmod 0440 /etc/sudoers.d/rafen-freeradius
    fi
}

secure_mysql() {
    if ! command_exists mysql; then
        return
    fi

    if ! run_mysql_root "SELECT 1;" >/dev/null 2>&1; then
        echo "NOTIFIKASI: Tidak bisa mengakses MySQL sebagai root. Set MYSQL_ROOT_PASSWORD untuk hardening."
        return
    fi

    run_mysql_root "DELETE FROM mysql.user WHERE User='';"
    run_mysql_root "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    run_mysql_root "DROP DATABASE IF EXISTS test;"
    run_mysql_root "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');"
    run_mysql_root "FLUSH PRIVILEGES;"
}

check_permissions() {
    local missing=0
    local app_user_uid
    local app_group_gid
    local clients_path
    local env_path
    local deploy_user_uid

    app_user_uid="$(id -u "$APP_USER" 2>/dev/null || true)"
    app_group_gid="$(getent group "$APP_GROUP" | cut -d: -f3 || true)"
    deploy_user_uid="$(id -u "$DEPLOY_USER" 2>/dev/null || true)"
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

    if [ -z "$deploy_user_uid" ]; then
        echo "NOTIFIKASI: user ${DEPLOY_USER} belum ada. Buat user atau set DEPLOY_USER."
        missing=1
    fi

    if ! grep -q '^RADIUS_RELOAD_COMMAND=".*"$' "$env_path"; then
        echo "NOTIFIKASI: RADIUS_RELOAD_COMMAND harus di-quote (contoh: \"sudo systemctl reload freeradius\")."
        missing=1
    fi

    if ! grep -q '^RADIUS_RESTART_COMMAND=".*"$' "$env_path"; then
        echo "NOTIFIKASI: RADIUS_RESTART_COMMAND harus di-quote (contoh: \"sudo systemctl restart freeradius\")."
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

prompt_deploy_password() {
    local value

    if [ -n "${DEPLOY_PASSWORD:-}" ]; then
        DEPLOY_PASSWORD="${DEPLOY_PASSWORD}"
        return
    fi

    read -r -s -p "Password untuk user ${DEPLOY_USER}: " value
    echo
    if [ -z "$value" ]; then
        echo "NOTIFIKASI: Password ${DEPLOY_USER} kosong, lewati pembuatan user."
        DEPLOY_PASSWORD=""
        return
    fi

    DEPLOY_PASSWORD="$value"
}

setup_deploy_user() {
    if id "$DEPLOY_USER" >/dev/null 2>&1; then
        echo "User ${DEPLOY_USER} sudah ada."
    else
        if [ -n "$DEPLOY_PASSWORD" ]; then
            useradd -m -s /bin/bash "$DEPLOY_USER"
            echo "${DEPLOY_USER}:${DEPLOY_PASSWORD}" | chpasswd
        fi
    fi

    if ! getent group "$APP_GROUP" >/dev/null 2>&1; then
        groupadd "$APP_GROUP"
    fi

    if id "$APP_USER" >/dev/null 2>&1; then
        usermod -a -G "$APP_GROUP" "$APP_USER"
    fi

    if id "$DEPLOY_USER" >/dev/null 2>&1; then
        usermod -a -G "$APP_GROUP" "$DEPLOY_USER"
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

setup_phpmyadmin() {
    if [ ! -d /usr/share/phpmyadmin ]; then
        echo "NOTIFIKASI: phpMyAdmin belum terinstall, skip konfigurasi."
        return
    fi

    if [ ! -f /etc/phpmyadmin/config.inc.php ]; then
        cat >/etc/phpmyadmin/config.inc.php <<'EOF'
<?php
declare(strict_types=1);

$cfg['blowfish_secret'] = '';
$cfg['Servers'][1]['auth_type'] = 'cookie';
$cfg['Servers'][1]['host'] = 'localhost';
$cfg['Servers'][1]['compress'] = false;
$cfg['Servers'][1]['AllowNoPassword'] = false;
EOF
    fi

    if grep -q "^\$cfg\['blowfish_secret'\] = '';" /etc/phpmyadmin/config.inc.php; then
        local secret
        secret="$(openssl rand -base64 32)"
        sed -i "s/^\$cfg\['blowfish_secret'\] = '';/\$cfg['blowfish_secret'] = '${secret}';/" /etc/phpmyadmin/config.inc.php
    fi

    if [ ! -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
        cat >/etc/apache2/conf-available/phpmyadmin.conf <<'EOF'
Alias /phpmyadmin /usr/share/phpmyadmin

<Directory /usr/share/phpmyadmin>
    Options SymLinksIfOwnerMatch
    DirectoryIndex index.php
    AllowOverride All
    Require all granted
</Directory>
EOF
    fi

    a2enconf phpmyadmin >/dev/null
    systemctl reload apache2
}

setup_systemd_services() {
    local created=0

    if [ -f /etc/systemd/system/rafen-queue.service ] && [ "$FORCE_SYSTEMD" != "1" ]; then
        echo "Service rafen-queue.service sudah ada, skip pembuatan."
    else
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
        created=1
    fi

    if [ -f /etc/systemd/system/rafen-schedule.service ] && [ "$FORCE_SYSTEMD" != "1" ]; then
        echo "Service rafen-schedule.service sudah ada, skip pembuatan."
    else
    cat >/etc/systemd/system/rafen-schedule.service <<EOF
[Unit]
Description=Rafen scheduler

[Service]
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan schedule:run
EOF
        created=1
    fi

    if [ -f /etc/systemd/system/rafen-schedule.timer ] && [ "$FORCE_SYSTEMD" != "1" ]; then
        echo "Timer rafen-schedule.timer sudah ada, skip pembuatan."
    else
    cat >/etc/systemd/system/rafen-schedule.timer <<'EOF'
[Unit]
Description=Rafen scheduler timer

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true

[Install]
WantedBy=timers.target
EOF
        created=1
    fi

    if [ "$created" -eq 1 ]; then
        systemctl daemon-reload
    fi

    enable_service_if_present "rafen-queue.service"
    enable_timer_if_present "rafen-schedule.timer"
}

setup_app() {
    chown -R "$APP_USER":"$APP_GROUP" "$APP_DIR"
    chmod -R g+rwX "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 2775 {} +

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
    enable_service_if_present "php8.4-fpm.service"
    enable_service_if_present "mariadb.service"
    enable_service_if_present "redis-server.service"
    enable_service_if_present "freeradius.service"
    enable_service_if_present "apache2.service"
}

main() {
    require_root

    prompt_deploy_password
    setup_deploy_user

    if command_exists apt-get; then
        export DEBIAN_FRONTEND=noninteractive
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
    secure_mysql
    setup_database
    setup_freeradius
    setup_apache
    setup_phpmyadmin
    setup_systemd_services
    setup_app
    check_permissions

    echo "Installation complete."
    echo "APP_URL=$(read_env APP_URL)"
}

main "$@"
