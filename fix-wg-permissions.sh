#!/usr/bin/env bash
# =============================================================================
# fix-wg-permissions.sh — Perbaiki permission WireGuard untuk RAFEN
#
# Jalankan ini di server yang sudah diinstall WireGuard tapi RAFEN tidak bisa
# sync peer (error "direktori tidak dapat ditulis" atau peer tidak muncul).
#
# Penggunaan:
#   sudo bash fix-wg-permissions.sh
# =============================================================================
set -euo pipefail

WG_INTERFACE="${WG_INTERFACE:-wg0}"
WG_CONFIG_PATH="${WG_CONFIG_PATH:-/etc/wireguard/wg0.conf}"
WG_CONF_OWNER="${WG_CONF_OWNER:-www-data}"
WG_CONF_GROUP="${WG_CONF_GROUP:-www-data}"
WG_KEY_DIR="/etc/wireguard"

info()  { echo "[INFO ] $*"; }
warn()  { echo "[WARN ] $*"; }
error() { echo "[ERROR] $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || error "Script harus dijalankan sebagai root: sudo bash $0"

# 1. Direktori /etc/wireguard — www-data harus bisa buat temp file di sini
info "Mengatur permission direktori $WG_KEY_DIR..."
chown root:"$WG_CONF_GROUP" "$WG_KEY_DIR"
chmod 0770 "$WG_KEY_DIR"

# 2. wg0.conf — www-data harus bisa baca & tulis
if [ -f "$WG_CONFIG_PATH" ]; then
    info "Mengatur permission $WG_CONFIG_PATH..."
    chown root:"$WG_CONF_GROUP" "$WG_CONFIG_PATH"
    chmod 0660 "$WG_CONFIG_PATH"
else
    warn "$WG_CONFIG_PATH tidak ditemukan. Jalankan install-wg.sh terlebih dahulu."
fi

# 3. server_private.key — www-data harus bisa baca (untuk fallback jika WG_SERVER_PRIVATE_KEY kosong)
local_privkey="${WG_KEY_DIR}/server_private.key"
if [ -f "$local_privkey" ]; then
    info "Mengatur permission $local_privkey..."
    chown root:"$WG_CONF_GROUP" "$local_privkey"
    chmod 0640 "$local_privkey"
fi

# 4. Sudoers — www-data bisa jalankan wg syncconf tanpa password
sudoers_file="/etc/sudoers.d/rafen-wireguard"
if [ ! -f "$sudoers_file" ]; then
    info "Membuat sudoers $sudoers_file..."
    cat <<EOF > "$sudoers_file"
# RAFEN WireGuard — allow www-data to apply peer changes without restart
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg syncconf ${WG_INTERFACE} *
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg-quick strip ${WG_INTERFACE}
EOF
    chmod 0440 "$sudoers_file"
    if visudo -c -f "$sudoers_file" >/dev/null 2>&1; then
        info "Sudoers valid: $sudoers_file"
    else
        warn "Sudoers mungkin tidak valid — periksa $sudoers_file"
    fi
else
    info "Sudoers sudah ada: $sudoers_file"
fi

# 5. Verifikasi hasil
echo ""
info "Verifikasi permission:"
ls -la "$WG_KEY_DIR"

# 6. Sync peer dari RAFEN database
echo ""
info "Menjalankan sync peer dari RAFEN database..."
APP_DIR="${APP_DIR:-/var/www/rafen}"
if [ -f "$APP_DIR/artisan" ]; then
    php "$APP_DIR/artisan" tinker --execute="
\$peers = \App\Models\WgPeer::where('is_active', true)->get();
app(\App\Services\WgPeerSynchronizer::class)->syncAll(\$peers);
echo 'OK: '.\$peers->count().' peer(s) synced';
" 2>&1 || warn "Sync gagal — cek log Laravel"
else
    warn "Artisan tidak ditemukan di $APP_DIR. Set APP_DIR=/path/to/rafen lalu jalankan ulang."
fi

# 7. Tampilkan status wg0
echo ""
info "Status WireGuard:"
wg show "$WG_INTERFACE" 2>/dev/null || warn "wg show gagal — pastikan wg0 sudah up"

echo ""
info "Selesai. Jika peer belum muncul di 'wg show', cek:"
info "  1. Peer sudah ditambahkan di web RAFEN (is_active = true)"
info "  2. PHP dapat menjalankan 'sudo wg syncconf' (cek sudoers di atas)"
info "  3. Log Laravel: storage/logs/laravel.log"
