#!/usr/bin/env bash
set -euo pipefail

HTTP_VHOST="${HTTP_VHOST:-/etc/apache2/sites-available/rafen.conf}"
HTTPS_VHOST="${HTTPS_VHOST:-/etc/apache2/sites-available/rafen-le-ssl.conf}"
APACHE_SERVICE="${APACHE_SERVICE:-apache2}"
ROUTER_IPS="${ROUTER_IPS:-103.38.104.218,103.38.104.219}"
ISOLIR_PATH="${ISOLIR_PATH:-/isolir/17}"
CAPTIVE_REGEX='(?:generate_204|gen_204|generate204(?:_[A-Za-z0-9-]+)?|connecttest\.txt|ncsi\.txt|hotspot-detect\.html|getDNList|getHttpDnsServerList|chat|route/mac/v1)'

MAX_REQUEST_WORKERS="${MAX_REQUEST_WORKERS:-150}"
PREFORK_START_SERVERS="${PREFORK_START_SERVERS:-6}"
PREFORK_MIN_SPARE_SERVERS="${PREFORK_MIN_SPARE_SERVERS:-6}"
PREFORK_MAX_SPARE_SERVERS="${PREFORK_MAX_SPARE_SERVERS:-12}"
PREFORK_MAX_CONNECTIONS_PER_CHILD="${PREFORK_MAX_CONNECTIONS_PER_CHILD:-1000}"

PHP_POOL_CONF="${PHP_POOL_CONF:-/etc/php/8.4/fpm/pool.d/www.conf}"
PHP_PM_MAX_CHILDREN="${PHP_PM_MAX_CHILDREN:-30}"
PHP_PM_START_SERVERS="${PHP_PM_START_SERVERS:-6}"
PHP_PM_MIN_SPARE_SERVERS="${PHP_PM_MIN_SPARE_SERVERS:-4}"
PHP_PM_MAX_SPARE_SERVERS="${PHP_PM_MAX_SPARE_SERVERS:-12}"

CHECK_ONLY=0
SKIP_RESTART=0

ACTIVE_MPM_CONF=""
ACTIVE_MPM_TYPE=""
ROUTER_IP_REGEX=""

info() {
    printf '\033[1;34m[INFO]\033[0m %s\n' "$1"
}

warn() {
    printf '\033[1;33m[WARN]\033[0m %s\n' "$1"
}

error() {
    printf '\033[1;31m[ERROR]\033[0m %s\n' "$1" >&2
}

usage() {
    cat <<'EOF'
Usage: ./install-captive-hardening.sh [options]

Options:
  --http-vhost <path>               Path vhost HTTP Apache
  --https-vhost <path>              Path vhost HTTPS Apache
  --router-ips <ip1,ip2,...>        IP router/sumber captive (comma-separated)
  --isolir-path <path>              Path isolir (default: /isolir/17)
  --max-request-workers <num>       Nilai MaxRequestWorkers Apache
  --php-pool-conf <path>            File pool PHP-FPM (default: /etc/php/8.4/fpm/pool.d/www.conf)
  --php-max-children <num>          pm.max_children
  --php-start-servers <num>         pm.start_servers
  --php-min-spare <num>             pm.min_spare_servers
  --php-max-spare <num>             pm.max_spare_servers
  --check-only                      Ubah file + validasi syntax tanpa restart service
  --skip-restart                    Ubah file + validasi syntax tanpa reload/restart service
  --help                            Tampilkan bantuan

Contoh:
  sudo ./install-captive-hardening.sh \
    --router-ips 103.38.104.218,103.38.104.219 \
    --isolir-path /isolir/17 \
    --max-request-workers 150 \
    --php-max-children 30
EOF
}

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        error "Jalankan sebagai root (contoh: sudo ./install-captive-hardening.sh)."
        exit 1
    fi
}

ensure_command() {
    local cmd="$1"

    if ! command -v "$cmd" >/dev/null 2>&1; then
        error "Perintah wajib tidak ditemukan: $cmd"
        exit 1
    fi
}

ensure_file_exists() {
    local file="$1"

    if [ ! -f "$file" ]; then
        error "File tidak ditemukan: $file"
        exit 1
    fi
}

backup_file() {
    local file="$1"
    local timestamp

    timestamp="$(date +%Y%m%d-%H%M%S)"
    cp "$file" "${file}.bak-${timestamp}"
}

parse_args() {
    while [ "$#" -gt 0 ]; do
        case "$1" in
            --http-vhost)
                HTTP_VHOST="$2"
                shift
                ;;
            --https-vhost)
                HTTPS_VHOST="$2"
                shift
                ;;
            --router-ips)
                ROUTER_IPS="$2"
                shift
                ;;
            --isolir-path)
                ISOLIR_PATH="$2"
                shift
                ;;
            --max-request-workers)
                MAX_REQUEST_WORKERS="$2"
                shift
                ;;
            --php-pool-conf)
                PHP_POOL_CONF="$2"
                shift
                ;;
            --php-max-children)
                PHP_PM_MAX_CHILDREN="$2"
                shift
                ;;
            --php-start-servers)
                PHP_PM_START_SERVERS="$2"
                shift
                ;;
            --php-min-spare)
                PHP_PM_MIN_SPARE_SERVERS="$2"
                shift
                ;;
            --php-max-spare)
                PHP_PM_MAX_SPARE_SERVERS="$2"
                shift
                ;;
            --check-only)
                CHECK_ONLY=1
                ;;
            --skip-restart)
                SKIP_RESTART=1
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            *)
                error "Argumen tidak dikenal: $1"
                usage
                exit 1
                ;;
        esac
        shift
    done
}

normalize_isolir_path() {
    if [ -z "$ISOLIR_PATH" ]; then
        error "--isolir-path tidak boleh kosong."
        exit 1
    fi

    if [[ "$ISOLIR_PATH" != /* ]]; then
        ISOLIR_PATH="/${ISOLIR_PATH}"
    fi
}

validate_ipv4() {
    local ip="$1"
    local a b c d

    if ! [[ "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
        return 1
    fi

    IFS='.' read -r a b c d <<<"$ip"
    for octet in "$a" "$b" "$c" "$d"; do
        if [ "$octet" -lt 0 ] || [ "$octet" -gt 255 ]; then
            return 1
        fi
    done

    return 0
}

build_router_ip_regex() {
    local normalized ip escaped
    local escaped_items=()

    normalized="$(printf '%s' "$ROUTER_IPS" | tr ',' ' ')"

    for ip in $normalized; do
        if ! validate_ipv4 "$ip"; then
            error "IP router tidak valid: $ip"
            exit 1
        fi

        escaped="${ip//./\\.}"
        escaped_items+=("$escaped")
    done

    if [ "${#escaped_items[@]}" -eq 0 ]; then
        error "Daftar --router-ips kosong."
        exit 1
    fi

    ROUTER_IP_REGEX="$(IFS='|'; printf '%s' "${escaped_items[*]}")"
}

escape_regex() {
    printf '%s' "$1" | sed -E 's/[][(){}.+*?^$|]/\\&/g'
}

ensure_customlog_skip_flag() {
    local file="$1"

    if ! grep -qE '^[[:space:]]*CustomLog[[:space:]]+' "$file"; then
        warn "CustomLog tidak ditemukan di $file. skip penambahan env=!skip_access_log"
        return
    fi

    sed -i -E '/^[[:space:]]*CustomLog[[:space:]]+/ {/env=!skip_access_log/! s#$# env=!skip_access_log#; }' "$file"
}

inject_captive_block() {
    local file="$1"
    local isolir_regex
    local tmp
    local block

    isolir_regex="$(escape_regex "$ISOLIR_PATH")"

    block="$(cat <<EOF
    # BEGIN CAPTIVE_HARDENING (managed by install-captive-hardening.sh)
    RewriteEngine on
    RewriteCond %{REQUEST_URI} ^/${CAPTIVE_REGEX}$ [NC]
    RewriteRule ^ - [R=204,L]

    RewriteCond %{REMOTE_ADDR} ^(?:${ROUTER_IP_REGEX})$
    RewriteCond %{REQUEST_URI} !^${isolir_regex}/?$ [NC]
    RewriteCond %{REQUEST_URI} !^/webhook(?:/|$) [NC]
    RewriteRule ^ ${ISOLIR_PATH} [R=302,L]

    SetEnvIfNoCase Request_URI "^/${CAPTIVE_REGEX}$" skip_access_log=1
    SetEnvIf Remote_Addr "^(?:${ROUTER_IP_REGEX})$" skip_access_log=1
    # END CAPTIVE_HARDENING
EOF
)"

    sed -i '/# BEGIN CAPTIVE_HARDENING (managed by install-captive-hardening.sh)/,/# END CAPTIVE_HARDENING/d' "$file"

    tmp="$(mktemp)"
    awk -v block="$block" '
        BEGIN { inserted = 0 }
        !inserted && $0 ~ /^[[:space:]]*RewriteEngine[[:space:]]+on[[:space:]]*$/ {
            print block
            inserted = 1
        }
        !inserted && $0 ~ /<\/VirtualHost>/ {
            print block
            inserted = 1
        }
        { print }
        END {
            if (!inserted) {
                print block
            }
        }
    ' "$file" >"$tmp"

    mv "$tmp" "$file"
}

detect_active_mpm_conf() {
    local mpm_module

    mpm_module="$(apachectl -M 2>/dev/null | awk '/mpm_(prefork|event|worker)_module/{print $1; exit}')"

    case "$mpm_module" in
        mpm_prefork_module)
            ACTIVE_MPM_TYPE="prefork"
            ACTIVE_MPM_CONF="/etc/apache2/mods-available/mpm_prefork.conf"
            ;;
        mpm_event_module)
            ACTIVE_MPM_TYPE="event"
            ACTIVE_MPM_CONF="/etc/apache2/mods-available/mpm_event.conf"
            ;;
        mpm_worker_module)
            ACTIVE_MPM_TYPE="worker"
            ACTIVE_MPM_CONF="/etc/apache2/mods-available/mpm_worker.conf"
            ;;
        *)
            warn "Tidak bisa mendeteksi MPM aktif. fallback ke mpm_prefork.conf."
            ACTIVE_MPM_TYPE="prefork"
            ACTIVE_MPM_CONF="/etc/apache2/mods-available/mpm_prefork.conf"
            ;;
    esac
}

set_space_directive() {
    local file="$1"
    local key="$2"
    local value="$3"

    if grep -qE "^[[:space:]#]*${key}[[:space:]]+" "$file"; then
        sed -i -E "s|^[[:space:]#]*${key}[[:space:]]+.*|${key} ${value}|" "$file"
    else
        printf '%s %s\n' "$key" "$value" >>"$file"
    fi
}

set_equals_directive() {
    local file="$1"
    local key="$2"
    local value="$3"
    local key_regex

    key_regex="${key//./\\.}"

    if grep -qE "^[[:space:];#]*${key_regex}[[:space:]]*=" "$file"; then
        sed -i -E "s|^[[:space:];#]*${key_regex}[[:space:]]*=.*|${key} = ${value}|" "$file"
    else
        printf '%s = %s\n' "$key" "$value" >>"$file"
    fi
}

tune_apache_mpm() {
    ensure_file_exists "$ACTIVE_MPM_CONF"

    set_space_directive "$ACTIVE_MPM_CONF" "MaxRequestWorkers" "$MAX_REQUEST_WORKERS"

    if [ "$ACTIVE_MPM_TYPE" = "prefork" ]; then
        set_space_directive "$ACTIVE_MPM_CONF" "StartServers" "$PREFORK_START_SERVERS"
        set_space_directive "$ACTIVE_MPM_CONF" "MinSpareServers" "$PREFORK_MIN_SPARE_SERVERS"
        set_space_directive "$ACTIVE_MPM_CONF" "MaxSpareServers" "$PREFORK_MAX_SPARE_SERVERS"
        set_space_directive "$ACTIVE_MPM_CONF" "MaxConnectionsPerChild" "$PREFORK_MAX_CONNECTIONS_PER_CHILD"
    fi
}

tune_php_fpm_pool() {
    ensure_file_exists "$PHP_POOL_CONF"

    set_equals_directive "$PHP_POOL_CONF" "pm.max_children" "$PHP_PM_MAX_CHILDREN"
    set_equals_directive "$PHP_POOL_CONF" "pm.start_servers" "$PHP_PM_START_SERVERS"
    set_equals_directive "$PHP_POOL_CONF" "pm.min_spare_servers" "$PHP_PM_MIN_SPARE_SERVERS"
    set_equals_directive "$PHP_POOL_CONF" "pm.max_spare_servers" "$PHP_PM_MAX_SPARE_SERVERS"
}

enable_apache_modules() {
    if command -v a2enmod >/dev/null 2>&1; then
        a2enmod rewrite setenvif >/dev/null
    fi
}

detect_php_fpm_service() {
    local running_service
    local installed_service

    running_service="$(systemctl list-units --type=service --state=running --no-legend 2>/dev/null | awk '/php[0-9]+\.[0-9]+-fpm\.service/{print $1; exit}')"
    if [ -n "$running_service" ]; then
        printf '%s' "$running_service"
        return
    fi

    installed_service="$(systemctl list-unit-files --type=service --no-legend 2>/dev/null | awk '/php[0-9]+\.[0-9]+-fpm\.service/{print $1; exit}')"
    if [ -n "$installed_service" ]; then
        printf '%s' "$installed_service"
        return
    fi

    if systemctl list-unit-files --type=service --no-legend 2>/dev/null | awk '{print $1}' | grep -Fxq 'php-fpm.service'; then
        printf '%s' "php-fpm.service"
        return
    fi

    printf '%s' ""
}

validate_and_reload() {
    local php_fpm_service

    apachectl -t

    if command -v php-fpm8.4 >/dev/null 2>&1; then
        php-fpm8.4 -t
    elif command -v php-fpm >/dev/null 2>&1; then
        php-fpm -t
    else
        warn "Binary php-fpm tidak ditemukan. skip php-fpm -t"
    fi

    if [ "$CHECK_ONLY" -eq 1 ] || [ "$SKIP_RESTART" -eq 1 ]; then
        info "Mode check-only/skip-restart aktif. Service tidak direload."
        return
    fi

    php_fpm_service="$(detect_php_fpm_service)"
    if [ -n "$php_fpm_service" ]; then
        systemctl restart "$php_fpm_service"
        info "Restart service: $php_fpm_service"
    else
        warn "Service PHP-FPM tidak terdeteksi otomatis. restart manual jika perlu."
    fi

    systemctl reload "$APACHE_SERVICE"
    info "Reload service: $APACHE_SERVICE"
}

main() {
    parse_args "$@"
    require_root

    ensure_command apachectl
    ensure_command sed
    ensure_command awk
    ensure_command systemctl

    normalize_isolir_path
    build_router_ip_regex

    ensure_file_exists "$HTTP_VHOST"
    ensure_file_exists "$HTTPS_VHOST"
    ensure_file_exists "$PHP_POOL_CONF"

    detect_active_mpm_conf
    ensure_file_exists "$ACTIVE_MPM_CONF"

    info "Backup file konfigurasi..."
    backup_file "$HTTP_VHOST"
    backup_file "$HTTPS_VHOST"
    backup_file "$ACTIVE_MPM_CONF"
    backup_file "$PHP_POOL_CONF"

    info "Aktifkan modul Apache yang diperlukan..."
    enable_apache_modules

    info "Terapkan hardening endpoint captive-check pada vhost HTTP/HTTPS..."
    inject_captive_block "$HTTP_VHOST"
    inject_captive_block "$HTTPS_VHOST"
    ensure_customlog_skip_flag "$HTTP_VHOST"
    ensure_customlog_skip_flag "$HTTPS_VHOST"

    info "Tuning Apache MPM (${ACTIVE_MPM_TYPE})..."
    tune_apache_mpm

    info "Tuning PHP-FPM pool..."
    tune_php_fpm_pool

    info "Validasi konfigurasi dan reload service..."
    validate_and_reload

    info "Selesai. Verifikasi cepat:"
    info "1) curl -i http://<domain>/generate_204"
    info "2) curl -i https://<domain>/generate_204 -k"
    info "3) tail -f /var/log/apache2/error.log /var/log/apache2/rafen_error.log"
}

main "$@"
