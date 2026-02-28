#!/usr/bin/env bash
# =============================================================================
# fix-wg-permissions.sh — Perbaiki semua izin & sudoers untuk RAFEN
#
# Jalankan ini di server yang sudah ada instalasi RAFEN + WireGuard +
# FreeRADIUS tapi mengalami masalah permission (peer tidak sync,
# clients.conf tidak bisa ditulis, dll).
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
APP_DIR="${APP_DIR:-/var/www/rafen}"
RADIUS_CLIENTS_DIR="${RADIUS_CLIENTS_DIR:-/etc/freeradius/3.0/clients.d}"

info()  { echo "[INFO ] $*"; }
warn()  { echo "[WARN ] $*"; }
ok()    { echo "[OK   ] $*"; }
error() { echo "[ERROR] $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || error "Script harus dijalankan sebagai root: sudo bash $0"

echo ""
echo "========================================================================"
echo " RAFEN — Fix Permissions & Sudoers"
echo "========================================================================"
echo ""

# ── 1. WireGuard directory & files ─────────────────────────────────────────
info "1. WireGuard — mengatur permission direktori & file..."

if [ -d "$WG_KEY_DIR" ]; then
    chown root:"$WG_CONF_GROUP" "$WG_KEY_DIR"
    chmod 0770 "$WG_KEY_DIR"
    ok "$WG_KEY_DIR → root:${WG_CONF_GROUP} 770"
else
    warn "$WG_KEY_DIR tidak ditemukan. Install WireGuard dulu."
fi

if [ -f "$WG_CONFIG_PATH" ]; then
    chown root:"$WG_CONF_GROUP" "$WG_CONFIG_PATH"
    chmod 0660 "$WG_CONFIG_PATH"
    ok "$WG_CONFIG_PATH → root:${WG_CONF_GROUP} 660"
else
    warn "$WG_CONFIG_PATH tidak ditemukan. Jalankan install-wg.sh terlebih dahulu."
fi

for keyfile in "${WG_KEY_DIR}/server_private.key" "${WG_KEY_DIR}/server_public.key"; do
    if [ -f "$keyfile" ]; then
        chown root:"$WG_CONF_GROUP" "$keyfile"
        [[ "$keyfile" == *private* ]] && chmod 0640 "$keyfile" || chmod 0644 "$keyfile"
        ok "$keyfile → permission diatur"
    fi
done

# ── 2. FreeRADIUS clients.d — www-data harus bisa tulis ────────────────────
info "2. FreeRADIUS — mengatur permission clients.d..."

if [ -d "$RADIUS_CLIENTS_DIR" ]; then
    # Tambahkan www-data ke group freerad jika belum
    FREERAD_GROUP="freerad"
    if getent group "$FREERAD_GROUP" >/dev/null 2>&1; then
        if ! id -nG "$WG_CONF_OWNER" | grep -qw "$FREERAD_GROUP"; then
            usermod -aG "$FREERAD_GROUP" "$WG_CONF_OWNER"
            ok "User $WG_CONF_OWNER ditambahkan ke group $FREERAD_GROUP"
        else
            ok "User $WG_CONF_OWNER sudah ada di group $FREERAD_GROUP"
        fi
        # Set direktori agar group freerad bisa tulis
        chown freerad:"$FREERAD_GROUP" "$RADIUS_CLIENTS_DIR"
        chmod 0770 "$RADIUS_CLIENTS_DIR"
        ok "$RADIUS_CLIENTS_DIR → freerad:${FREERAD_GROUP} 770"
    else
        warn "Group freerad tidak ditemukan. Pastikan FreeRADIUS terinstall."
    fi

    # laravel.conf: milik www-data, readable oleh freerad (via group)
    LARAVEL_CONF="${RADIUS_CLIENTS_DIR}/laravel.conf"
    if [ -f "$LARAVEL_CONF" ]; then
        chown "$WG_CONF_OWNER":"$FREERAD_GROUP" "$LARAVEL_CONF"
        chmod 0664 "$LARAVEL_CONF"
        ok "$LARAVEL_CONF → ${WG_CONF_OWNER}:${FREERAD_GROUP} 664"
    fi
else
    warn "$RADIUS_CLIENTS_DIR tidak ditemukan. Install FreeRADIUS dulu."
fi

# ── 3. FreeRADIUS log — www-data harus bisa baca ───────────────────────────
info "3. FreeRADIUS log — permission baca..."
RADIUS_LOG="/var/log/freeradius/radius.log"
if [ -f "$RADIUS_LOG" ]; then
    # Tambahkan www-data ke group adm (yang bisa baca log freeradius)
    if getent group adm >/dev/null 2>&1; then
        if ! id -nG "$WG_CONF_OWNER" | grep -qw "adm"; then
            usermod -aG adm "$WG_CONF_OWNER"
            ok "User $WG_CONF_OWNER ditambahkan ke group adm (akses log)"
        else
            ok "User $WG_CONF_OWNER sudah ada di group adm"
        fi
    fi
fi

# ── 4. Laravel storage & bootstrap/cache ───────────────────────────────────
info "4. Laravel — mengatur permission storage & cache..."
if [ -d "$APP_DIR" ]; then
    chown -R "$WG_CONF_OWNER":"$WG_CONF_GROUP" "${APP_DIR}/storage"
    chmod -R 0775 "${APP_DIR}/storage"
    chown -R "$WG_CONF_OWNER":"$WG_CONF_GROUP" "${APP_DIR}/bootstrap/cache"
    chmod -R 0775 "${APP_DIR}/bootstrap/cache"
    ok "${APP_DIR}/storage dan bootstrap/cache → diatur"
else
    warn "APP_DIR=$APP_DIR tidak ditemukan."
fi

# ── 5. Sudoers WireGuard ────────────────────────────────────────────────────
info "5. Sudoers — WireGuard sync..."
SUDOERS_WG="/etc/sudoers.d/rafen-wireguard"
cat > "$SUDOERS_WG" << SUDOERS_EOF
# RAFEN WireGuard — allow www-data to apply peer changes without restart
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg syncconf ${WG_INTERFACE} *
${WG_CONF_OWNER} ALL=NOPASSWD:/usr/bin/wg-quick strip ${WG_INTERFACE}
SUDOERS_EOF
chmod 0440 "$SUDOERS_WG"
if visudo -c -f "$SUDOERS_WG" >/dev/null 2>&1; then
    ok "Sudoers WireGuard: $SUDOERS_WG"
else
    warn "Sudoers WireGuard mungkin tidak valid — periksa $SUDOERS_WG"
fi

# ── 6. Sudoers FreeRADIUS ──────────────────────────────────────────────────
info "6. Sudoers — FreeRADIUS reload + sync-clients wrapper..."

# Buat wrapper script agar artisan radius:sync-clients bisa dijalankan via sudo
WRAPPER="/usr/local/bin/rafen-sync-radius-clients"
cat > "$WRAPPER" << WRAPPER_EOF
#!/bin/bash
exec /usr/bin/php ${APP_DIR}/artisan radius:sync-clients "\$@"
WRAPPER_EOF
chmod 0755 "$WRAPPER"
ok "Wrapper dibuat: $WRAPPER"

SUDOERS_FR="/etc/sudoers.d/rafen-freeradius"
cat > "$SUDOERS_FR" << SUDOERS_EOF
# RAFEN FreeRADIUS — allow www-data to reload/restart and sync clients
Defaults:${WG_CONF_OWNER} !requiretty
${WG_CONF_OWNER} ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius
${WG_CONF_OWNER} ALL=NOPASSWD:${WRAPPER}
SUDOERS_EOF
chmod 0440 "$SUDOERS_FR"
if visudo -c -f "$SUDOERS_FR" >/dev/null 2>&1; then
    ok "Sudoers FreeRADIUS: $SUDOERS_FR"
else
    warn "Sudoers FreeRADIUS mungkin tidak valid — periksa $SUDOERS_FR"
fi

# ── 7. Systemd drop-in WireGuard ──────────────────────────────────────────
info "7. Systemd drop-in — pertahankan permission WireGuard setelah restart..."
DROPIN_DIR="/etc/systemd/system/wg-quick@${WG_INTERFACE}.service.d"
DROPIN_FILE="${DROPIN_DIR}/rafen-permissions.conf"
mkdir -p "$DROPIN_DIR"
cat > "$DROPIN_FILE" << DROPIN_EOF
# RAFEN — pertahankan permission wg0.conf dan direktori setelah wg-quick restart
[Service]
ExecStartPost=/bin/bash -c 'chown root:${WG_CONF_GROUP} ${WG_CONFIG_PATH} && chmod 660 ${WG_CONFIG_PATH} && chown root:${WG_CONF_GROUP} ${WG_KEY_DIR} && chmod 770 ${WG_KEY_DIR}'
DROPIN_EOF
systemctl daemon-reload 2>/dev/null && ok "systemctl daemon-reload OK" || warn "daemon-reload gagal"

# ── 8. Reload grup untuk www-data (activate group changes) ─────────────────
info "8. Menerapkan perubahan grup..."
# Grup baru aktif untuk proses baru; nginx/php-fpm perlu restart
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '')"
if [ -n "$PHP_VER" ] && systemctl list-units --type=service 2>/dev/null | grep -q "php${PHP_VER}-fpm"; then
    systemctl restart "php${PHP_VER}-fpm" && ok "PHP-FPM restarted (grup baru aktif)" || warn "Gagal restart PHP-FPM"
fi
if systemctl list-units --type=service 2>/dev/null | grep -q "nginx.service"; then
    systemctl reload nginx && ok "Nginx reloaded" || warn "Gagal reload Nginx"
fi
if systemctl list-units --type=service 2>/dev/null | grep -q "apache2.service"; then
    systemctl reload apache2 && ok "Apache reloaded" || warn "Gagal reload Apache"
fi

# ── 9. Sync RADIUS clients.conf dari DB ────────────────────────────────────
info "9. Sync RADIUS clients.conf dari database..."
if [ -f "${APP_DIR}/artisan" ]; then
    sudo -u "$WG_CONF_OWNER" "$WRAPPER" 2>/dev/null \
        && ok "RADIUS clients.conf berhasil di-sync" \
        || {
            # Fallback langsung sebagai root
            php "${APP_DIR}/artisan" radius:sync-clients 2>&1 \
                && ok "RADIUS clients.conf sync (via root)" \
                || warn "Sync RADIUS clients gagal — jalankan manual: php ${APP_DIR}/artisan radius:sync-clients"
        }
else
    warn "Artisan tidak ditemukan di $APP_DIR"
fi

# ── 10. Sync RADIUS replies dari DB ────────────────────────────────────────
info "10. Sync radcheck/radreply (PPP + Hotspot + Voucher)..."
if [ -f "${APP_DIR}/artisan" ]; then
    php "${APP_DIR}/artisan" radius:sync-replies 2>&1 \
        && ok "radcheck/radreply berhasil di-sync" \
        || warn "Sync radcheck/radreply gagal — jalankan manual: php ${APP_DIR}/artisan radius:sync-replies"
fi

# ── 11. FreeRADIUS default site — aktifkan sql di blok session{} ───────────
info "11. FreeRADIUS — mengaktifkan Simultaneous-Use check (session sql)..."
FR_DEFAULT="/etc/freeradius/3.0/sites-available/default"
if [ -f "$FR_DEFAULT" ]; then
    # Aktifkan baris '#\tsql' di dalam blok session {} menjadi '\tsql'
    if grep -qP '^\s*session\s*\{' "$FR_DEFAULT" && grep -qP '^#\s*sql' "$FR_DEFAULT"; then
        # Hanya aktifkan '#\tsql' yang ada di dalam blok session{}
        python3 - "$FR_DEFAULT" <<'PYEOF'
import sys, re

path = sys.argv[1]
with open(path) as f:
    content = f.read()

# Aktifkan '# sql' atau '#\tsql' di dalam blok session {}
# dengan mengganti hanya satu kemunculan di blok session
result = re.sub(
    r'(session\s*\{[^}]*?)#\s*(sql)',
    lambda m: m.group(1) + m.group(2),
    content,
    count=1,
    flags=re.DOTALL
)

if result != content:
    with open(path, 'w') as f:
        f.write(result)
    print("[OK   ] sql diaktifkan di blok session{}")
else:
    print("[INFO ] sql sudah aktif atau tidak perlu perubahan di blok session{}")
PYEOF
    else
        ok "Blok session{} sudah dikonfigurasi atau file tidak sesuai pola, lewati."
    fi

    # Validasi konfigurasi
    if freeradius -XC 2>&1 | grep -q "Configuration appears to be OK"; then
        ok "Konfigurasi FreeRADIUS valid."
        systemctl restart freeradius && ok "FreeRADIUS di-restart." || warn "Gagal restart FreeRADIUS."
    else
        warn "Konfigurasi FreeRADIUS mungkin bermasalah — cek: sudo freeradius -XC"
    fi
else
    warn "$FR_DEFAULT tidak ditemukan — lewati konfigurasi Simultaneous-Use."
fi

# ── 12. Sync WireGuard peers dari DB ────────────────────────────────────────
info "12. Sync WireGuard peer dari database..."
if [ -f "${APP_DIR}/artisan" ]; then
    php "${APP_DIR}/artisan" tinker --execute="
\$peers = \App\Models\WgPeer::where('is_active', true)->get();
app(\App\Services\WgPeerSynchronizer::class)->syncAll(\$peers);
echo 'OK: '.\$peers->count().' peer(s) synced';
" 2>&1 | tail -1 | grep -v "^$" || warn "Sync peer gagal — cek storage/logs/laravel.log"
fi

# ── Ringkasan ────────────────────────────────────────────────────────────────
echo ""
echo "========================================================================"
info "Verifikasi permission:"
ls -la "$WG_KEY_DIR"
echo ""
if [ -d "$RADIUS_CLIENTS_DIR" ]; then
    ls -la "$RADIUS_CLIENTS_DIR"
fi
echo ""
info "Sudoers aktif:"
ls -la /etc/sudoers.d/rafen-* 2>/dev/null || true
echo ""
if command -v wg >/dev/null 2>&1 && ip link show "$WG_INTERFACE" >/dev/null 2>&1; then
    info "Status WireGuard:"
    wg show "$WG_INTERFACE" 2>/dev/null || warn "wg show gagal"
fi
echo ""
echo "========================================================================"
info "Selesai. Semua permission dan sudoers telah dikonfigurasi."
info "Jika masih ada masalah, cek: ${APP_DIR}/storage/logs/laravel.log"
echo "========================================================================"
