#!/usr/bin/env bash
# =============================================================================
# install-wg.sh — Installer lengkap RAFEN (WireGuard + FreeRADIUS permissions)
#
# Penggunaan:
#   sudo bash install-wg.sh              # Install fresh (package + keys + config + permissions)
#   sudo bash install-wg.sh --config-only  # Tulis ulang wg0.conf saja (tanpa install ulang)
#   sudo bash install-wg.sh --fix-perms    # Perbaiki permission saja (sama dengan fix-wg-permissions.sh)
#
# Environment variables (opsional — ada default):
#   WG_PORT          : UDP port WireGuard       (default: 51820)
#   WG_SERVER_IP     : IP server di tunnel      (default: 10.0.0.1)
#   WG_SERVER_ADDRESS: Address + prefix         (default: 10.0.0.1/24)
#   WG_NETWORK       : Subnet tunnel            (default: 10.0.0.0/24)
#   WG_INTERFACE     : Nama interface           (default: wg0)
#   WG_CONFIG_PATH   : Path wg0.conf            (default: /etc/wireguard/wg0.conf)
#   WG_INTERFACE_OUT : Interface keluar NAT     (default: auto-detect via ip route)
#   WG_CONF_OWNER    : User webserver           (default: www-data)
#   WG_CONF_GROUP    : Group webserver          (default: www-data)
#   APP_DIR          : Path instalasi RAFEN     (default: /var/www/rafen)
#
# =============================================================================
set -euo pipefail

# ── Default values ─────────────────────────────────────────────────────────
WG_PORT="${WG_PORT:-51820}"
WG_SERVER_IP="${WG_SERVER_IP:-10.0.0.1}"
WG_SERVER_ADDRESS="${WG_SERVER_ADDRESS:-10.0.0.1/24}"
WG_NETWORK="${WG_NETWORK:-10.0.0.0/24}"
WG_INTERFACE="${WG_INTERFACE:-wg0}"
WG_CONFIG_PATH="${WG_CONFIG_PATH:-/etc/wireguard/wg0.conf}"
WG_INTERFACE_OUT="${WG_INTERFACE_OUT:-}"
WG_CONF_OWNER="${WG_CONF_OWNER:-www-data}"
WG_CONF_GROUP="${WG_CONF_GROUP:-www-data}"
WG_KEY_DIR="/etc/wireguard"
APP_DIR="${APP_DIR:-/var/www/rafen}"
RADIUS_CLIENTS_DIR="${RADIUS_CLIENTS_DIR:-/etc/freeradius/3.0/clients.d}"

MODE="full"
case "${1:-}" in
    --config-only) MODE="config-only" ;;
    --fix-perms)   MODE="fix-perms" ;;
    "")            MODE="full" ;;
    *) echo "Penggunaan: $0 [--config-only|--fix-perms]"; exit 1 ;;
esac

# ── Helpers ────────────────────────────────────────────────────────────────
info()  { echo "[INFO ] $*"; }
warn()  { echo "[WARN ] $*"; }
ok()    { echo "[OK   ] $*"; }
error() { echo "[ERROR] $*" >&2; exit 1; }

require_root() {
    [ "$(id -u)" -eq 0 ] || error "Script harus dijalankan sebagai root: sudo bash $0"
}

detect_outbound_interface() {
    if [ -n "$WG_INTERFACE_OUT" ]; then
        echo "$WG_INTERFACE_OUT"; return
    fi
    local iface
    iface="$(ip -4 route list default 2>/dev/null | awk '{print $5}' | head -n1)"
    echo "${iface:-eth0}"
}

command_exists() { command -v "$1" >/dev/null 2>&1; }

# ── Phase 1: Install packages ──────────────────────────────────────────────
install_packages() {
    info "Menginstall paket WireGuard..."
    apt-get update -y -q
    apt-get install -y -q wireguard wireguard-tools iptables-persistent
    info "Paket WireGuard berhasil diinstall."
}

# ── Phase 2: Generate server keypair ──────────────────────────────────────
generate_server_keys() {
    local privkey_file="${WG_KEY_DIR}/server_private.key"
    local pubkey_file="${WG_KEY_DIR}/server_public.key"

    if [ -f "$privkey_file" ] && [ -s "$privkey_file" ]; then
        info "Server keys sudah ada di ${privkey_file}, melewati generate."
        chown root:"$WG_CONF_GROUP" "$privkey_file" 2>/dev/null || true
        chmod 0640 "$privkey_file"
        return
    fi

    info "Generate server keypair WireGuard..."
    mkdir -p "$WG_KEY_DIR"
    umask 077
    wg genkey | tee "$privkey_file" | wg pubkey > "$pubkey_file"
    chown root:"$WG_CONF_GROUP" "$privkey_file" "$pubkey_file" 2>/dev/null || true
    chmod 0640 "$privkey_file"
    chmod 0644 "$pubkey_file"

    info "Server keypair berhasil digenerate:"
    echo ""
    echo "========================================================"
    echo " SERVER PUBLIC KEY (dibutuhkan di script MikroTik):"
    echo "  $(cat "$pubkey_file")"
    echo "========================================================"
    echo ""
}

# ── Phase 3: Write wg0.conf ────────────────────────────────────────────────
write_server_config() {
    local privkey_file="${WG_KEY_DIR}/server_private.key"
    local iface
    iface="$(detect_outbound_interface)"

    [ -n "$iface" ] || { warn "Tidak dapat mendeteksi interface keluar. Menggunakan eth0."; iface="eth0"; }
    info "Interface keluar untuk NAT: $iface"

    [ -f "$privkey_file" ] && [ -s "$privkey_file" ] \
        || error "Server private key tidak ditemukan di $privkey_file."

    local server_privkey
    server_privkey="$(cat "$privkey_file")"

    if [ -f "$WG_CONFIG_PATH" ]; then
        local backup="${WG_CONFIG_PATH}.bak.$(date +%Y%m%d%H%M%S)"
        cp "$WG_CONFIG_PATH" "$backup"
        info "Backup config lama: $backup"
    fi

    info "Menulis konfigurasi server ke $WG_CONFIG_PATH..."
    cat > "$WG_CONFIG_PATH" << EOF
# ============================================================
# Managed by RAFEN install-wg.sh
# Generated: $(date)
# JANGAN diedit manual — gunakan web interface RAFEN untuk mengelola peer
# ============================================================

[Interface]
PrivateKey = ${server_privkey}
Address = ${WG_SERVER_ADDRESS}
ListenPort = ${WG_PORT}
PostUp = iptables -t nat -A POSTROUTING -s ${WG_NETWORK} -o ${iface} -j MASQUERADE; iptables -A FORWARD -i ${WG_INTERFACE} -j ACCEPT; iptables -A FORWARD -o ${WG_INTERFACE} -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -s ${WG_NETWORK} -o ${iface} -j MASQUERADE; iptables -D FORWARD -i ${WG_INTERFACE} -j ACCEPT; iptables -D FORWARD -o ${WG_INTERFACE} -j ACCEPT

# ============================================================
# Peer dikelola oleh RAFEN Laravel (sync dari web interface)
# ============================================================
EOF

    chown root:"$WG_CONF_GROUP" "$WG_CONFIG_PATH" 2>/dev/null || true
    chmod 0660 "$WG_CONFIG_PATH"
    ok "wg0.conf berhasil ditulis."
}

# ── Phase 4: Setup ALL permissions & sudoers ──────────────────────────────
setup_permissions() {
    local conf_dir
    conf_dir="$(dirname "$WG_CONFIG_PATH")"

    info "Mengatur semua permission untuk RAFEN..."

    # 4a. WireGuard direktori & files
    if getent group "$WG_CONF_GROUP" >/dev/null 2>&1; then
        chown root:"$WG_CONF_GROUP" "$conf_dir" 2>/dev/null || true
        chmod 0770 "$conf_dir"
        ok "$conf_dir → root:${WG_CONF_GROUP} 770"

        [ -f "$WG_CONFIG_PATH" ] && {
            chown root:"$WG_CONF_GROUP" "$WG_CONFIG_PATH" 2>/dev/null || true
            chmod 0660 "$WG_CONFIG_PATH"
            ok "$WG_CONFIG_PATH → root:${WG_CONF_GROUP} 660"
        }

        local privkey_file="${WG_KEY_DIR}/server_private.key"
        [ -f "$privkey_file" ] && {
            chown root:"$WG_CONF_GROUP" "$privkey_file" 2>/dev/null || true
            chmod 0640 "$privkey_file"
        }

        local pubkey_file="${WG_KEY_DIR}/server_public.key"
        [ -f "$pubkey_file" ] && {
            chown root:"$WG_CONF_GROUP" "$pubkey_file" 2>/dev/null || true
            chmod 0644 "$pubkey_file"
        }
    fi

    # 4b. FreeRADIUS clients.d — www-data harus bisa tulis
    if [ -d "$RADIUS_CLIENTS_DIR" ]; then
        local FREERAD_GROUP="freerad"
        if getent group "$FREERAD_GROUP" >/dev/null 2>&1; then
            # Tambahkan www-data ke group freerad
            if ! id -nG "$WG_CONF_OWNER" | grep -qw "$FREERAD_GROUP"; then
                usermod -aG "$FREERAD_GROUP" "$WG_CONF_OWNER"
                ok "User $WG_CONF_OWNER ditambahkan ke group $FREERAD_GROUP"
            else
                ok "User $WG_CONF_OWNER sudah ada di group $FREERAD_GROUP"
            fi
            chown freerad:"$FREERAD_GROUP" "$RADIUS_CLIENTS_DIR"
            chmod 0770 "$RADIUS_CLIENTS_DIR"
            ok "$RADIUS_CLIENTS_DIR → freerad:${FREERAD_GROUP} 770"

            local LARAVEL_CONF="${RADIUS_CLIENTS_DIR}/laravel.conf"
            [ -f "$LARAVEL_CONF" ] && {
                chown "$WG_CONF_OWNER":"$FREERAD_GROUP" "$LARAVEL_CONF"
                chmod 0664 "$LARAVEL_CONF"
                ok "$LARAVEL_CONF → ${WG_CONF_OWNER}:${FREERAD_GROUP} 664"
            }
        else
            warn "Group freerad tidak ditemukan. Install FreeRADIUS dulu."
        fi
    else
        warn "$RADIUS_CLIENTS_DIR tidak ditemukan — lewati konfigurasi FreeRADIUS."
    fi

    # 4c. Laravel storage & cache
    if [ -d "$APP_DIR" ]; then
        chown -R "$WG_CONF_OWNER":"$WG_CONF_GROUP" "${APP_DIR}/storage"
        chmod -R 0775 "${APP_DIR}/storage"
        chown -R "$WG_CONF_OWNER":"$WG_CONF_GROUP" "${APP_DIR}/bootstrap/cache"
        chmod -R 0775 "${APP_DIR}/bootstrap/cache"
        ok "${APP_DIR}/storage & bootstrap/cache → ${WG_CONF_OWNER}:${WG_CONF_GROUP} 775"
    fi

    # 4d. Sudoers WireGuard
    local SUDOERS_WG="/etc/sudoers.d/rafen-wireguard"
    cat > "$SUDOERS_WG" << SUDOERS_EOF
# RAFEN WireGuard — allow www-data to apply peer changes without restart
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg syncconf ${WG_INTERFACE} *
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg-quick strip ${WG_INTERFACE}
SUDOERS_EOF
    chmod 0440 "$SUDOERS_WG"
    if command_exists visudo && visudo -c -f "$SUDOERS_WG" >/dev/null 2>&1; then
        ok "Sudoers WireGuard: $SUDOERS_WG"
    else
        warn "Sudoers WireGuard mungkin tidak valid — periksa $SUDOERS_WG"
    fi

    # 4e. Sudoers FreeRADIUS (dengan wrapper agar tidak ada masalah karakter ':')
    local WRAPPER="/usr/local/bin/rafen-sync-radius-clients"
    cat > "$WRAPPER" << WRAPPER_EOF
#!/bin/bash
exec /usr/bin/php ${APP_DIR}/artisan radius:sync-clients "\$@"
WRAPPER_EOF
    chmod 0755 "$WRAPPER"
    ok "Wrapper dibuat: $WRAPPER"

    local SUDOERS_FR="/etc/sudoers.d/rafen-freeradius"
    cat > "$SUDOERS_FR" << SUDOERS_EOF
# RAFEN FreeRADIUS — allow www-data to reload/restart and sync clients
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius
${WG_CONF_OWNER} ALL=NOPASSWD:${WRAPPER}
SUDOERS_EOF
    chmod 0440 "$SUDOERS_FR"
    if command_exists visudo && visudo -c -f "$SUDOERS_FR" >/dev/null 2>&1; then
        ok "Sudoers FreeRADIUS: $SUDOERS_FR"
    else
        warn "Sudoers FreeRADIUS mungkin tidak valid — periksa $SUDOERS_FR"
    fi

    # 4f. Systemd drop-in — pertahankan permission setelah wg-quick restart
    local dropin_dir="/etc/systemd/system/wg-quick@${WG_INTERFACE}.service.d"
    local dropin_file="${dropin_dir}/rafen-permissions.conf"
    mkdir -p "$dropin_dir"
    cat > "$dropin_file" << DROPIN_EOF
# RAFEN — pertahankan permission wg0.conf dan direktori setelah wg-quick restart
[Service]
ExecStartPost=/bin/bash -c 'chown root:${WG_CONF_GROUP} ${WG_CONFIG_PATH} && chmod 660 ${WG_CONFIG_PATH} && chown root:${WG_CONF_GROUP} $(dirname ${WG_CONFIG_PATH}) && chmod 770 $(dirname ${WG_CONFIG_PATH})'
DROPIN_EOF
    systemctl daemon-reload 2>/dev/null && ok "systemctl daemon-reload OK" || warn "daemon-reload gagal"
}

# ── Phase 5: IP forwarding ─────────────────────────────────────────────────
enable_ip_forwarding() {
    info "Mengaktifkan IP forwarding..."
    sysctl -w net.ipv4.ip_forward=1 >/dev/null

    if grep -qE '^\s*#\s*net\.ipv4\.ip_forward\s*=\s*1' /etc/sysctl.conf 2>/dev/null; then
        sed -i 's/^\s*#\s*\(net\.ipv4\.ip_forward\s*=\s*1\)/\1/' /etc/sysctl.conf
    elif ! grep -qE '^\s*net\.ipv4\.ip_forward\s*=\s*1' /etc/sysctl.conf 2>/dev/null; then
        echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
    fi

    sysctl -p /etc/sysctl.conf >/dev/null 2>&1 || true
    ok "IP forwarding aktif (persistent)."
}

# ── Phase 6: Tune PHP ──────────────────────────────────────────────────────
tune_php() {
    local php_ver
    php_ver="${PHP_VERSION:-$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '8.4')}"
    info "Menyesuaikan php.ini untuk PHP ${php_ver}..."

    _update_ini() {
        local file="$1"
        [ -f "$file" ] || return 0
        info "  Update $file"
        sed -i "s/^upload_max_filesize.*/upload_max_filesize = 64M/"  "$file"
        sed -i "s/^post_max_size.*/post_max_size = 64M/"              "$file"
        sed -i "s/^memory_limit.*/memory_limit = 128M/"               "$file"
        sed -i "s/^max_execution_time.*/max_execution_time = 300/"    "$file"
    }

    _update_ini "/etc/php/${php_ver}/cli/php.ini"

    if systemctl list-units --type=service 2>/dev/null | grep -q "php${php_ver}-fpm"; then
        _update_ini "/etc/php/${php_ver}/fpm/php.ini"
        systemctl restart "php${php_ver}-fpm" && ok "PHP-FPM restarted." || warn "Gagal restart PHP-FPM."
    fi

    if systemctl list-units --type=service 2>/dev/null | grep -q "apache2"; then
        _update_ini "/etc/php/${php_ver}/apache2/php.ini"
        systemctl restart apache2 && ok "Apache restarted." || warn "Gagal restart Apache."
    fi

    if systemctl list-units --type=service 2>/dev/null | grep -q "nginx"; then
        systemctl reload nginx && ok "Nginx reloaded." || warn "Gagal reload Nginx."
    fi

    ok "PHP tuning selesai."
}

# ── Phase 7: Enable WireGuard service ─────────────────────────────────────
enable_service() {
    info "Mengaktifkan dan memulai wg-quick@${WG_INTERFACE}..."
    systemctl enable "wg-quick@${WG_INTERFACE}.service" >/dev/null 2>&1 || true
    systemctl start  "wg-quick@${WG_INTERFACE}.service" \
        && ok "Service wg-quick@${WG_INTERFACE} aktif." \
        || warn "Gagal start service. Cek: journalctl -u wg-quick@${WG_INTERFACE} -n 20"
}

restart_service() {
    if systemctl is-active --quiet "wg-quick@${WG_INTERFACE}.service"; then
        info "Interface aktif — menggunakan wg syncconf (tanpa memutus koneksi)..."
        bash -c "wg syncconf ${WG_INTERFACE} <(wg-quick strip ${WG_INTERFACE})" 2>/dev/null \
            && ok "wg syncconf berhasil." \
            || {
                warn "wg syncconf gagal, mencoba restart..."
                systemctl restart "wg-quick@${WG_INTERFACE}.service" && ok "Service di-restart."
            }
    else
        systemctl start "wg-quick@${WG_INTERFACE}.service" \
            && ok "Service dimulai." \
            || warn "Gagal start service."
    fi
}

# ── Phase 8: Restart PHP-FPM/webserver agar grup baru aktif ───────────────
restart_webserver() {
    info "Restart webserver agar perubahan grup aktif..."
    local php_ver
    php_ver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '')"

    [ -n "$php_ver" ] && systemctl list-units --type=service 2>/dev/null | grep -q "php${php_ver}-fpm" && {
        systemctl restart "php${php_ver}-fpm" && ok "PHP-FPM restarted." || warn "Gagal restart PHP-FPM."
    }
    systemctl list-units --type=service 2>/dev/null | grep -q "nginx.service" && {
        systemctl reload nginx && ok "Nginx reloaded." || warn "Gagal reload Nginx."
    }
    systemctl list-units --type=service 2>/dev/null | grep -q "apache2.service" && {
        systemctl reload apache2 && ok "Apache reloaded." || warn "Gagal reload Apache."
    }
}

# ── Phase 9: Initial RADIUS sync ──────────────────────────────────────────
sync_radius() {
    [ -f "${APP_DIR}/artisan" ] || return 0

    info "Sync RADIUS clients.conf dari database..."
    php "${APP_DIR}/artisan" radius:sync-clients 2>&1 \
        && ok "RADIUS clients.conf berhasil di-sync" \
        || warn "Sync RADIUS clients gagal — jalankan manual: php ${APP_DIR}/artisan radius:sync-clients"

    info "Sync radcheck/radreply (PPP + Hotspot + Voucher)..."
    php "${APP_DIR}/artisan" radius:sync-replies 2>&1 \
        && ok "radcheck/radreply berhasil di-sync" \
        || warn "Sync replies gagal — jalankan manual: php ${APP_DIR}/artisan radius:sync-replies"
}

# ── Print .env hint ────────────────────────────────────────────────────────
print_env_hint() {
    local pubkey_file="${WG_KEY_DIR}/server_public.key"
    local privkey_file="${WG_KEY_DIR}/server_private.key"
    local iface
    iface="$(detect_outbound_interface)"

    local pubkey="" privkey=""
    [ -f "$pubkey_file"  ] && pubkey="$(cat "$pubkey_file")"
    [ -f "$privkey_file" ] && privkey="$(cat "$privkey_file")"

    local public_ip=""
    public_ip="$(curl -s --max-time 5 https://api.ipify.org 2>/dev/null || true)"

    echo ""
    echo "================================================================"
    echo " RAFEN .env — Tambahkan variabel berikut ke file .env Laravel:"
    echo "================================================================"
    echo ""
    echo "# WireGuard VPN"
    echo "WG_HOST=${public_ip:-<IP_PUBLIK_SERVER>}"
    echo "WG_SERVER_IP=${WG_SERVER_IP}"
    echo "WG_SERVER_ADDRESS=${WG_SERVER_ADDRESS}"
    echo "WG_SERVER_PRIVATE_KEY=${privkey}"
    echo "WG_SERVER_PUBLIC_KEY=${pubkey}"
    echo "WG_LISTEN_PORT=${WG_PORT}"
    echo "WG_INTERFACE=${WG_INTERFACE}"
    echo "WG_CONFIG_PATH=${WG_CONFIG_PATH}"
    echo "WG_POOL_START=10.0.0.2"
    echo "WG_POOL_END=10.0.0.254"
    echo "WG_POST_UP=iptables -t nat -A POSTROUTING -s ${WG_NETWORK} -o ${iface} -j MASQUERADE; iptables -A FORWARD -i ${WG_INTERFACE} -j ACCEPT; iptables -A FORWARD -o ${WG_INTERFACE} -j ACCEPT"
    echo "WG_POST_DOWN=iptables -t nat -D POSTROUTING -s ${WG_NETWORK} -o ${iface} -j MASQUERADE; iptables -D FORWARD -i ${WG_INTERFACE} -j ACCEPT; iptables -D FORWARD -o ${WG_INTERFACE} -j ACCEPT"
    echo ""
    echo "# FreeRADIUS"
    echo "RADIUS_SERVER_IP=${WG_SERVER_IP}"
    echo "RADIUS_CLIENTS_PATH=/etc/freeradius/3.0/clients.d/laravel.conf"
    echo "RADIUS_LOG_PATH=/var/log/freeradius/radius.log"
    echo 'RADIUS_RELOAD_COMMAND="sudo systemctl reload freeradius"'
    echo 'RADIUS_RESTART_COMMAND="sudo systemctl restart freeradius"'
    echo ""
    echo "================================================================"
    echo " Setelah update .env, jalankan:"
    echo "   php artisan config:clear && php artisan config:cache"
    echo ""
    echo " Lalu buka web RAFEN: Pengaturan > WireGuard"
    echo " untuk menambah peer dan generate script MikroTik."
    echo "================================================================"
}

# ── Main ───────────────────────────────────────────────────────────────────
main() {
    require_root

    case "$MODE" in
        fix-perms)
            echo ""
            echo "========================================================"
            echo " RAFEN — Fix Permissions (mode: fix-perms)"
            echo "========================================================"
            echo ""
            setup_permissions
            restart_webserver
            sync_radius
            echo ""
            ok "Fix permissions selesai."
            ;;

        config-only)
            echo ""
            info "Mode: config-only (tidak install paket, tidak generate keys)"
            write_server_config
            setup_permissions
            restart_service
            echo ""
            ok "Config-only selesai. wg0.conf diperbarui."
            ;;

        full)
            echo ""
            echo "========================================================"
            echo " RAFEN WireGuard + Permissions Installer"
            echo "========================================================"
            echo ""

            install_packages
            generate_server_keys
            write_server_config
            setup_permissions
            enable_ip_forwarding
            tune_php
            enable_service
            restart_webserver
            sync_radius
            print_env_hint

            echo ""
            ok "Instalasi selesai. WireGuard server siap di port ${WG_PORT}/UDP."
            info "Pastikan port ${WG_PORT}/UDP terbuka di firewall/security group server."
            ;;
    esac
}

main "$@"
