#!/usr/bin/env bash
set -euo pipefail

OVPN_PORT="${OVPN_PORT:-1194}"
OVPN_PROTO="${OVPN_PROTO:-udp}"
OVPN_NETWORK="${OVPN_NETWORK:-10.8.0.0}"
OVPN_NETMASK="${OVPN_NETMASK:-255.255.255.0}"
OVPN_DNS="${OVPN_DNS:-1.1.1.1,8.8.8.8}"
OVPN_CLIENT_NAME="${OVPN_CLIENT_NAME:-mikrotik}"
OVPN_INTERFACE="${OVPN_INTERFACE:-}"
EASYRSA_DIR="/etc/openvpn/easy-rsa"
SERVER_DIR="/etc/openvpn/server"
CLIENT_DIR="/etc/openvpn/client-configs"
CCD_DIR="/etc/openvpn/ccd"
CCD_OWNER="${CCD_OWNER:-www-data}"
CCD_GROUP="${CCD_GROUP:-www-data}"
AUTH_FILE="/etc/openvpn/ovpn-users"
AUTH_OWNER="${OVPN_AUTH_OWNER:-www-data}"
AUTH_GROUP="${OVPN_AUTH_GROUP:-www-data}"
AUTH_SCRIPT="/etc/openvpn/checkpsw.sh"
config_only=0

if [ "${1:-}" = "--config-only" ]; then
    config_only=1
elif [ -n "${1:-}" ]; then
    echo "Usage: $0 [--config-only]"
    exit 1
fi

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "Please run as root (sudo)."
        exit 1
    fi
}

detect_interface() {
    if [ -n "$OVPN_INTERFACE" ]; then
        echo "$OVPN_INTERFACE"
        return
    fi

    ip -4 route list default | awk '{print $5}' | head -n1
}

install_packages() {
    apt-get update -y
    apt-get install -y openvpn easy-rsa iptables-persistent
}

setup_easy_rsa() {
    if [ "$config_only" -eq 1 ]; then
        return
    fi
    mkdir -p "$EASYRSA_DIR"
    rm -rf "${EASYRSA_DIR:?}/"*
    cp -r /usr/share/easy-rsa/* "$EASYRSA_DIR"
    pushd "$EASYRSA_DIR" >/dev/null
    ./easyrsa init-pki
    EASYRSA_BATCH=1 ./easyrsa build-ca nopass
    EASYRSA_BATCH=1 ./easyrsa build-server-full server nopass
    EASYRSA_BATCH=1 ./easyrsa build-client-full "$OVPN_CLIENT_NAME" nopass
    ./easyrsa gen-dh
    # No tls-auth for Mikrotik compatibility.
    popd >/dev/null
}

install_server_files() {
    if [ "$config_only" -eq 1 ]; then
        return
    fi
    mkdir -p "$SERVER_DIR"
    cp "$EASYRSA_DIR/pki/ca.crt" "$SERVER_DIR"
    cp "$EASYRSA_DIR/pki/dh.pem" "$SERVER_DIR"
    cp "$EASYRSA_DIR/pki/issued/server.crt" "$SERVER_DIR"
    cp "$EASYRSA_DIR/pki/private/server.key" "$SERVER_DIR"
}

write_server_config() {
    cat > "$SERVER_DIR/server.conf" <<EOF
port ${OVPN_PORT}
proto ${OVPN_PROTO}
dev tun
user nobody
group nogroup
persist-key
persist-tun
keepalive 10 120
topology subnet
server ${OVPN_NETWORK} ${OVPN_NETMASK}
ifconfig-pool-persist ipp.txt
client-config-dir ${CCD_DIR}
dh dh.pem
ca ca.crt
cert server.crt
key server.key
cipher AES-256-CBC
auth SHA1
data-ciphers AES-256-CBC:AES-128-CBC
data-ciphers-fallback AES-256-CBC
verify-client-cert none
username-as-common-name
auth-user-pass-verify /etc/openvpn/checkpsw.sh via-file
script-security 2
push "dhcp-option DNS ${OVPN_DNS//,/ }"
push "redirect-gateway def1 bypass-dhcp"
verb 3
EOF
}

enable_ip_forwarding() {
    sysctl -w net.ipv4.ip_forward=1
    if ! grep -q '^net.ipv4.ip_forward=1' /etc/sysctl.conf; then
        echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
    fi
}

setup_nat() {
    local iface
    iface="$(detect_interface)"
    if [ -z "$iface" ]; then
        echo "Tidak bisa mendeteksi interface keluar. Set OVPN_INTERFACE lalu jalankan ulang."
        exit 1
    fi

    iptables -t nat -C POSTROUTING -s "${OVPN_NETWORK}/24" -o "$iface" -j MASQUERADE 2>/dev/null || \
        iptables -t nat -A POSTROUTING -s "${OVPN_NETWORK}/24" -o "$iface" -j MASQUERADE

    netfilter-persistent save
}

setup_ccd() {
    mkdir -p "$CCD_DIR"
    chown "$CCD_OWNER":"$CCD_GROUP" "$CCD_DIR"
    chmod 0775 "$CCD_DIR"
}

write_auth_files() {
    mkdir -p "/etc/openvpn"
    if [ ! -f "$AUTH_FILE" ]; then
        touch "$AUTH_FILE"
    fi

    cat > "$AUTH_SCRIPT" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

PASSFILE="/etc/openvpn/ovpn-users"
CRED_FILE="${1:-}"

if [ -z "$CRED_FILE" ] || [ ! -f "$CRED_FILE" ]; then
    exit 1
fi

if [ ! -r "$PASSFILE" ]; then
    exit 1
fi

USERNAME="$(sed -n '1p' "$CRED_FILE" | tr -d '\r')"
PASSWORD="$(sed -n '2p' "$CRED_FILE" | tr -d '\r')"

if [ -z "$USERNAME" ] || [ -z "$PASSWORD" ]; then
    exit 1
fi

grep -Fxq "${USERNAME} ${PASSWORD}" "$PASSFILE"
EOF

    chown root:root "$AUTH_SCRIPT"
    chmod 0755 "$AUTH_SCRIPT"

    chown "$AUTH_OWNER":"$AUTH_GROUP" "$AUTH_FILE"
    chmod 0644 "$AUTH_FILE"
}

write_client_config() {
    if [ "$config_only" -eq 1 ]; then
        return
    fi
    mkdir -p "$CLIENT_DIR"
    cat > "$CLIENT_DIR/${OVPN_CLIENT_NAME}.ovpn" <<EOF
client
dev tun
proto ${OVPN_PROTO}
remote $(curl -fsSL ifconfig.me) ${OVPN_PORT}
resolv-retry infinite
nobind
persist-key
persist-tun
remote-cert-tls server
cipher AES-256-CBC
auth SHA1
verb 3

<ca>
$(cat "$EASYRSA_DIR/pki/ca.crt")
</ca>
<cert>
$(cat "$EASYRSA_DIR/pki/issued/${OVPN_CLIENT_NAME}.crt")
</cert>
<key>
$(cat "$EASYRSA_DIR/pki/private/${OVPN_CLIENT_NAME}.key")
</key>
EOF
}

enable_service() {
    systemctl enable --now openvpn-server@server.service
}

main() {
    require_root
    install_packages
    setup_easy_rsa
    install_server_files
    write_server_config
    setup_ccd
    write_auth_files
    enable_ip_forwarding
    setup_nat
    write_client_config
    enable_service

    if [ "$config_only" -eq 1 ]; then
        echo "OpenVPN config-only selesai. server.conf diperbarui."
    else
        echo "OpenVPN siap. Client file: ${CLIENT_DIR}/${OVPN_CLIENT_NAME}.ovpn"
    fi
}

main "$@"
