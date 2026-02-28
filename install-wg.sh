#!/usr/bin/env bash
# =============================================================================
# install-wg.sh — Installer WireGuard server untuk RAFEN
#
# Penggunaan:
#   sudo bash install-wg.sh              # Install fresh (package + keys + config)
#   sudo bash install-wg.sh --config-only  # Tulis ulang wg0.conf saja (tanpa install ulang)
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

CONFIG_ONLY=0
if [ "${1:-}" = "--config-only" ]; then
    CONFIG_ONLY=1
elif [ -n "${1:-}" ]; then
    echo "Penggunaan: $0 [--config-only]"
    exit 1
fi

# ── Helpers ────────────────────────────────────────────────────────────────
info()  { echo "[INFO ] $*"; }
warn()  { echo "[WARN ] $*"; }
error() { echo "[ERROR] $*" >&2; exit 1; }

require_root() {
    [ "$(id -u)" -eq 0 ] || error "Script harus dijalankan sebagai root: sudo bash $0"
}

detect_outbound_interface() {
    if [ -n "$WG_INTERFACE_OUT" ]; then
        echo "$WG_INTERFACE_OUT"
        return
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
    info "Paket berhasil diinstall."
}

# ── Phase 2: Generate server keypair ──────────────────────────────────────
generate_server_keys() {
    local privkey_file="${WG_KEY_DIR}/server_private.key"
    local pubkey_file="${WG_KEY_DIR}/server_public.key"

    if [ -f "$privkey_file" ] && [ -s "$privkey_file" ]; then
        info "Server keys sudah ada di ${privkey_file}, melewati generate."
        # Pastikan permission key benar meski sudah ada
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
    info "  Private key : $privkey_file"
    info "  Public key  : $pubkey_file"
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

    if [ -z "$iface" ]; then
        warn "Tidak dapat mendeteksi interface keluar. Set WG_INTERFACE_OUT lalu jalankan ulang."
        iface="eth0"
    fi
    info "Interface keluar untuk NAT: $iface"

    if [ ! -f "$privkey_file" ] || [ ! -s "$privkey_file" ]; then
        error "Server private key tidak ditemukan di $privkey_file. Jalankan tanpa --config-only terlebih dahulu."
    fi

    local server_privkey
    server_privkey="$(cat "$privkey_file")"

    # Backup existing config
    if [ -f "$WG_CONFIG_PATH" ]; then
        local backup="${WG_CONFIG_PATH}.bak.$(date +%Y%m%d%H%M%S)"
        cp "$WG_CONFIG_PATH" "$backup"
        info "Backup config lama: $backup"
    fi

    info "Menulis konfigurasi server ke $WG_CONFIG_PATH..."

    cat > "$WG_CONFIG_PATH" <<EOF
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
    info "wg0.conf berhasil ditulis."
}

# ── Phase 4: Setup permissions & sudoers ──────────────────────────────────
setup_permissions() {
    local conf_dir
    conf_dir="$(dirname "$WG_CONFIG_PATH")"

    info "Mengatur permission direktori WireGuard..."

    if getent group "$WG_CONF_GROUP" >/dev/null 2>&1; then
        # Direktori: root:www-data 750 — www-data bisa masuk dan baca, tidak bisa list tanpa akses grup
        chown root:"$WG_CONF_GROUP" "$conf_dir" 2>/dev/null || true
        chmod 0770 "$conf_dir"

        # wg0.conf: root:www-data 660 — www-data perlu baca & TULIS
        if [ -f "$WG_CONFIG_PATH" ]; then
            chown root:"$WG_CONF_GROUP" "$WG_CONFIG_PATH" 2>/dev/null || true
            chmod 0660 "$WG_CONFIG_PATH"
        fi

        # server_private.key: root:www-data 640 — www-data bisa baca (untuk export pubkey)
        local privkey_file="${WG_KEY_DIR}/server_private.key"
        if [ -f "$privkey_file" ]; then
            chown root:"$WG_CONF_GROUP" "$privkey_file" 2>/dev/null || true
            chmod 0640 "$privkey_file"
        fi

        # server_public.key: root:www-data 644 — readable oleh semua
        local pubkey_file="${WG_KEY_DIR}/server_public.key"
        if [ -f "$pubkey_file" ]; then
            chown root:"$WG_CONF_GROUP" "$pubkey_file" 2>/dev/null || true
            chmod 0644 "$pubkey_file"
        fi
    fi

    # Sudoers — selalu tulis ulang untuk memastikan isinya benar
    local sudoers_file="/etc/sudoers.d/rafen-wireguard"
    info "Menulis sudoers untuk wg syncconf..."
    cat <<EOF > "$sudoers_file"
# RAFEN WireGuard — allow www-data to apply peer changes without restart
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg syncconf ${WG_INTERFACE} *
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg-quick strip ${WG_INTERFACE}
EOF
    chmod 0440 "$sudoers_file"
    if command_exists visudo && visudo -c -f "$sudoers_file" >/dev/null 2>&1; then
        info "Sudoers valid: $sudoers_file"
    else
        warn "Sudoers mungkin tidak valid — periksa $sudoers_file"
    fi

    # Systemd drop-in — pastikan permission wg0.conf bertahan setelah restart service
    local dropin_dir="/etc/systemd/system/wg-quick@${WG_INTERFACE}.service.d"
    local dropin_file="${dropin_dir}/rafen-permissions.conf"
    info "Membuat systemd drop-in untuk mempertahankan permission setelah restart..."
    mkdir -p "$dropin_dir"
    cat <<EOF > "$dropin_file"
# RAFEN — pastikan wg0.conf dapat ditulis oleh www-data setelah service restart
[Service]
ExecStartPost=/bin/bash -c 'chown root:${WG_CONF_GROUP} ${WG_CONFIG_PATH} && chmod 660 ${WG_CONFIG_PATH}'
EOF
    systemctl daemon-reload 2>/dev/null && info "systemctl daemon-reload OK" || warn "daemon-reload gagal"
}

# ── Phase 5: IP forwarding ─────────────────────────────────────────────────
enable_ip_forwarding() {
    info "Mengaktifkan IP forwarding..."

    # Aktifkan langsung tanpa perlu reboot
    sysctl -w net.ipv4.ip_forward=1 >/dev/null

    # Pastikan entry aktif (non-komentar) ada di /etc/sysctl.conf
    if grep -qE '^\s*#\s*net\.ipv4\.ip_forward\s*=\s*1' /etc/sysctl.conf 2>/dev/null; then
        # Uncomment baris yang ada
        sed -i 's/^\s*#\s*\(net\.ipv4\.ip_forward\s*=\s*1\)/\1/' /etc/sysctl.conf
        info "net.ipv4.ip_forward=1 di-uncomment di /etc/sysctl.conf"
    elif ! grep -qE '^\s*net\.ipv4\.ip_forward\s*=\s*1' /etc/sysctl.conf 2>/dev/null; then
        echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
        info "net.ipv4.ip_forward=1 ditambahkan ke /etc/sysctl.conf"
    else
        info "net.ipv4.ip_forward=1 sudah ada di /etc/sysctl.conf"
    fi

    # Apply agar persistent tanpa reboot
    sysctl -p /etc/sysctl.conf >/dev/null 2>&1 || true
    info "IP forwarding aktif (persistent)."
}

# ── Phase 6: Enable service ────────────────────────────────────────────────
enable_service() {
    info "Mengaktifkan dan memulai wg-quick@${WG_INTERFACE}..."
    systemctl enable "wg-quick@${WG_INTERFACE}.service" >/dev/null 2>&1 || true
    systemctl start  "wg-quick@${WG_INTERFACE}.service" || {
        warn "Gagal start service. Cek log: journalctl -u wg-quick@${WG_INTERFACE} -n 20"
    }
    info "Service wg-quick@${WG_INTERFACE} aktif."
}

# ── Phase 6b: Restart / reload service (config-only) ──────────────────────
restart_service() {
    if systemctl is-active --quiet "wg-quick@${WG_INTERFACE}.service"; then
        info "Interface aktif — menggunakan wg syncconf (tanpa memutus koneksi)..."
        bash -c "wg syncconf ${WG_INTERFACE} <(wg-quick strip ${WG_INTERFACE})" 2>/dev/null \
            && info "wg syncconf berhasil." \
            || {
                warn "wg syncconf gagal, mencoba restart..."
                systemctl restart "wg-quick@${WG_INTERFACE}.service" && info "Service di-restart."
            }
    else
        info "Memulai wg-quick@${WG_INTERFACE}..."
        systemctl start "wg-quick@${WG_INTERFACE}.service" || \
            warn "Gagal start service. Cek log: journalctl -u wg-quick@${WG_INTERFACE} -n 20"
    fi
}

# ── Phase 7: Tune PHP ini ──────────────────────────────────────────────────
tune_php() {
    local php_ver
    # Auto-detect versi PHP aktif; fallback ke 8.4
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

    # CLI
    _update_ini "/etc/php/${php_ver}/cli/php.ini"

    # FPM
    if systemctl list-units --type=service 2>/dev/null | grep -q "php${php_ver}-fpm"; then
        _update_ini "/etc/php/${php_ver}/fpm/php.ini"
        systemctl restart "php${php_ver}-fpm" && info "  PHP-FPM restarted." || warn "  Gagal restart PHP-FPM."
    fi

    # Apache
    if systemctl list-units --type=service 2>/dev/null | grep -q "apache2"; then
        _update_ini "/etc/php/${php_ver}/apache2/php.ini"
        systemctl restart apache2 && info "  Apache restarted." || warn "  Gagal restart Apache."
    fi

    # Nginx (tidak punya php.ini sendiri — sudah ditangani FPM di atas)
    if systemctl list-units --type=service 2>/dev/null | grep -q "nginx"; then
        systemctl restart nginx && info "  Nginx restarted." || warn "  Gagal restart Nginx."
    fi

    info "PHP tuning selesai."
}

# ── Print .env hint ────────────────────────────────────────────────────────
print_env_hint() {
    local pubkey_file="${WG_KEY_DIR}/server_public.key"
    local privkey_file="${WG_KEY_DIR}/server_private.key"
    local iface
    iface="$(detect_outbound_interface)"

    local pubkey=""
    local privkey=""
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
    echo "# Update RADIUS server IP ke IP tunnel WireGuard:"
    echo "RADIUS_SERVER_IP=${WG_SERVER_IP}"
    echo ""
    echo "================================================================"
    echo " Setelah update .env, jalankan:"
    echo "   php artisan config:clear"
    echo "   php artisan config:cache"
    echo ""
    echo " Lalu buka web RAFEN: Pengaturan > WireGuard"
    echo " untuk menambah peer dan generate script MikroTik."
    echo "================================================================"
}

# ── Main ───────────────────────────────────────────────────────────────────
main() {
    require_root

    if [ "$CONFIG_ONLY" -eq 1 ]; then
        info "Mode: config-only (tidak install paket, tidak generate keys)"
        write_server_config
        setup_permissions
        restart_service
        echo ""
        info "Config-only selesai. wg0.conf diperbarui."
        return
    fi

    info "================================================================"
    info " RAFEN WireGuard Installer"
    info "================================================================"
    echo ""

    install_packages
    generate_server_keys
    write_server_config
    setup_permissions
    enable_ip_forwarding
    tune_php
    enable_service
    print_env_hint

    echo ""
    info "WireGuard server siap di port ${WG_PORT}/UDP."
    info "Pastikan port ${WG_PORT}/UDP terbuka di firewall/security group server."
}

main "$@"
