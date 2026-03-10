#!/usr/bin/env bash
set -euo pipefail

APP_USER="${APP_USER:-www-data}"
CHECK_ONLY=0
ROOT_RUNNER=""

info() {
    printf '\033[1;34m[INFO]\033[0m %s\n' "$1"
}

warn() {
    printf '\033[1;33m[WARN]\033[0m %s\n' "$1"
}

error() {
    printf '\033[1;31m[ERROR]\033[0m %s\n' "$1" >&2
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

usage() {
    cat <<'EOF'
Usage: ./install-snmpwalk.sh [options]

Options:
  --check-only          Hanya verifikasi, tanpa install paket
  --app-user <user>     User aplikasi untuk verifikasi eksekusi (default: www-data)
  --help                Tampilkan bantuan
EOF
}

parse_args() {
    while [ "$#" -gt 0 ]; do
        case "$1" in
            --check-only)
                CHECK_ONLY=1
                ;;
            --app-user)
                if [ "$#" -lt 2 ]; then
                    error "Parameter --app-user membutuhkan nilai."
                    exit 1
                fi
                APP_USER="$2"
                shift
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

prepare_root_runner() {
    if [ "$(id -u)" -eq 0 ]; then
        ROOT_RUNNER=""
        return
    fi

    if ! command_exists sudo; then
        error "Script butuh akses root. Jalankan sebagai root atau install sudo."
        exit 1
    fi

    if ! sudo -n true >/dev/null 2>&1; then
        error "sudo non-interaktif tidak tersedia. Jalankan script sebagai root agar tanpa interaksi."
        exit 1
    fi

    ROOT_RUNNER="sudo -n"
}

run_as_root() {
    local cmd="$1"

    if [ -n "$ROOT_RUNNER" ]; then
        sudo -n bash -lc "$cmd"
    else
        bash -lc "$cmd"
    fi
}

install_with_apt() {
    info "Terdeteksi apt-get (Debian/Ubuntu)."
    run_as_root "export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a; apt-get update -y"
    run_as_root "export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a; apt-get install -y snmp"
}

install_with_dnf() {
    info "Terdeteksi dnf (RHEL/Alma/Rocky/Fedora)."
    run_as_root "dnf install -y net-snmp-utils"
}

install_with_yum() {
    info "Terdeteksi yum (CentOS/RHEL lama)."
    run_as_root "yum install -y net-snmp-utils"
}

install_with_apk() {
    info "Terdeteksi apk (Alpine)."
    run_as_root "apk add --no-cache net-snmp-tools"
}

install_with_zypper() {
    info "Terdeteksi zypper (openSUSE/SLES)."
    run_as_root "zypper --non-interactive install net-snmp"
}

install_with_pacman() {
    info "Terdeteksi pacman (Arch)."
    run_as_root "pacman --noconfirm -Sy net-snmp"
}

install_snmpwalk() {
    if command_exists snmpwalk; then
        info "snmpwalk sudah terpasang, melewati instalasi paket."
        return
    fi

    if command_exists apt-get; then
        install_with_apt
        return
    fi

    if command_exists dnf; then
        install_with_dnf
        return
    fi

    if command_exists yum; then
        install_with_yum
        return
    fi

    if command_exists apk; then
        install_with_apk
        return
    fi

    if command_exists zypper; then
        install_with_zypper
        return
    fi

    if command_exists pacman; then
        install_with_pacman
        return
    fi

    error "Package manager tidak dikenali. Install net-snmp/snmp secara manual."
    exit 1
}

verify_snmpwalk() {
    if ! command_exists snmpwalk; then
        error "snmpwalk belum tersedia di PATH setelah proses install."
        exit 1
    fi

    local version_line
    version_line="$(snmpwalk --version 2>&1 | head -n 1 || true)"

    if [ -z "$version_line" ]; then
        error "snmpwalk terdeteksi tapi gagal dieksekusi."
        exit 1
    fi

    info "snmpwalk siap digunakan: $version_line"
}

run_as_app_user() {
    local user="$1"
    local cmd="$2"

    if [ "$(id -u)" -eq 0 ]; then
        su -s /bin/bash "$user" -c "$cmd"
        return $?
    fi

    if command_exists sudo && sudo -n true >/dev/null 2>&1; then
        sudo -n -u "$user" bash -lc "$cmd"
        return $?
    fi

    return 1
}

verify_app_user_access() {
    if ! id "$APP_USER" >/dev/null 2>&1; then
        warn "User aplikasi '$APP_USER' tidak ditemukan, skip verifikasi eksekusi per-user."
        return
    fi

    if run_as_app_user "$APP_USER" 'command -v snmpwalk >/dev/null 2>&1'; then
        info "User aplikasi '$APP_USER' bisa menjalankan snmpwalk."
    else
        warn "Tidak bisa memverifikasi snmpwalk sebagai user '$APP_USER'."
    fi
}

verify_php_proc_open() {
    if ! command_exists php; then
        warn "PHP CLI tidak ditemukan, skip verifikasi proc_open."
        return
    fi

    local proc_status
    proc_status="$(php -r '
$disabled = array_filter(array_map("trim", explode(",", (string) ini_get("disable_functions"))));
$enabled = function_exists("proc_open") && !in_array("proc_open", $disabled, true);
echo $enabled ? "enabled" : "disabled";
' 2>/dev/null || true)"

    if [ "$proc_status" = "enabled" ]; then
        info "PHP proc_open aktif (sesuai kebutuhan Laravel Process)."
    else
        warn "PHP proc_open tidak aktif. Fitur polling SNMP dari aplikasi bisa gagal."
    fi
}

main() {
    parse_args "$@"

    if [ "$CHECK_ONLY" -eq 1 ]; then
        info "Mode check-only: tanpa instalasi paket."
    else
        prepare_root_runner
        install_snmpwalk
    fi

    verify_snmpwalk
    verify_app_user_access
    verify_php_proc_open

    info "Selesai. Installer SNMP walk berjalan non-interaktif."
}

main "$@"
