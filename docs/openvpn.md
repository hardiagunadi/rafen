# Dokumentasi OpenVPN Server

## Arsitektur

Aplikasi ini (Laravel) berjalan di **server berbeda** dari VPS OpenVPN.
- **Server Laravel** : mengelola data client, generate script Mikrotik, tulis CCD/auth file
- **VPS OpenVPN**    : menjalankan OpenVPN server, membaca CCD & auth file dari path yang dikonfigurasi di `.env`

---

## Konfigurasi `.env` yang Relevan

```env
OVPN_HOST=           # IP/hostname publik VPS OpenVPN (digunakan di script Mikrotik)
OVPN_SERVER_IP=10.8.0.1
OVPN_PORT=1194
OVPN_PROTO=tcp       # WAJIB tcp — UDP menyebabkan "poll error" di Mikrotik RouterOS
OVPN_NETWORK=10.8.0.0
OVPN_NETMASK=255.255.255.0
OVPN_POOL_START=10.8.0.2
OVPN_POOL_END=10.8.0.254
OVPN_CCD_PATH=/etc/openvpn/ccd
OVPN_AUTH_USERS_PATH=/etc/openvpn/ovpn-users
OVPN_ROUTE_DST=      # IP tujuan routing opsional (mis. 10.0.0.1/32)
```

---

## Konfigurasi Server OpenVPN (`/etc/openvpn/server/server.conf`)

```conf
port 1194
proto tcp-server        # WAJIB tcp-server — bukan udp
dev tun
...
cipher AES-256-CBC
auth SHA1
data-ciphers AES-256-CBC:AES-128-CBC
data-ciphers-fallback AES-256-CBC
verify-client-cert none
username-as-common-name
auth-user-pass-verify /etc/openvpn/checkpsw.sh via-file
script-security 2
# TIDAK ada push "redirect-gateway" — menyebabkan poll error di Mikrotik
# TIDAK ada push "dhcp-option DNS"  — menyebabkan poll error di Mikrotik
verb 3
```

### Kenapa TIDAK boleh ada `push "redirect-gateway"` dan `push "dhcp-option DNS"`

Ketika server meng-push kedua directive ini, Mikrotik RouterOS akan:
1. Mencoba redirect **semua** traffic internet melalui tunnel VPN
2. Mengganti DNS server secara paksa

Ini menyebabkan **routing loop** yang membuat koneksi VPN putus terus-menerus
dengan log `poll error` atau `could not connect` di Mikrotik.

---

## Script Mikrotik yang Di-generate Aplikasi

Script yang di-generate **sengaja minimal** — tidak ada `auth=`, `cipher=`, `protocol=`, `port=`, `mode=` — agar Mikrotik melakukan **auto-negotiate** dengan server:

```
/interface ovpn-client add disabled=no connect-to="<HOST>" name="ovpn-<cn>" user="<user>" password="<pass>" comment="IPADDR : <ip>"
```

### Kenapa parameter auth/cipher tidak di-set eksplisit

- Mikrotik ROS v6/v7 yang di-set `auth=sha1 cipher=aes256-cbc` secara eksplisit
  sering mengalami **NCP (cipher negotiation) conflict** dengan OpenVPN modern
- Tanpa parameter tersebut, Mikrotik negotiate otomatis → lebih kompatibel
- Ini adalah pendekatan yang sama dengan aplikasi sejenis yang terbukti berjalan

---

## Instalasi Fresh di VPS Baru

```bash
# Clone repo, lalu jalankan:
sudo OVPN_PROTO=tcp bash /path/to/install-ovpn.sh
```

Script akan:
1. Install paket (`openvpn`, `easy-rsa`, `iptables-persistent`)
2. Generate PKI & sertifikat
3. Tulis `server.conf` dengan proto tcp-server (tanpa push redirect/DNS)
4. Setup CCD dir, auth file, IP forwarding, NAT
5. Enable & start service

---

## Update Config di VPS yang Sudah Berjalan (Setelah `git pull`)

Gunakan flag `--config-only` — **tidak** menyentuh PKI/sertifikat, tidak install ulang paket:

```bash
sudo OVPN_PROTO=tcp bash /path/to/install-ovpn.sh --config-only
```

Yang dilakukan `--config-only`:
1. Tulis ulang `/etc/openvpn/server/server.conf`
2. Update direktori CCD (`/etc/openvpn/ccd`)
3. Update auth script (`/etc/openvpn/checkpsw.sh`)
4. **Auto-restart** service OpenVPN (detect `openvpn-server@server` atau `openvpn.service`)

### Perintah restart manual (jika diperlukan)

```bash
# Cek nama service yang aktif:
sudo systemctl list-units | grep openvpn

# Restart sesuai nama service:
sudo systemctl restart openvpn-server@server.service
# atau:
sudo systemctl restart openvpn.service
```

---

## Troubleshooting

### Mikrotik log: `poll error`
- **Penyebab**: Server menggunakan `proto udp` atau ada `push "redirect-gateway"`
- **Solusi**: Jalankan `install-ovpn.sh --config-only` di VPS

### Mikrotik log: `could not connect`
- **Penyebab**: Port 1194 TCP belum terbuka di firewall VPS, atau server belum di-restart setelah config diubah
- **Cek**: `ss -tlnp | grep 1194` — harus ada output (bukan kosong)
- **Cek**: `sudo systemctl status openvpn-server@server.service`

### Auth gagal (username/password salah)
- Cek file `/etc/openvpn/ovpn-users` di VPS — format: `username password` per baris
- Pastikan `OVPN_AUTH_USERS_PATH` di `.env` sesuai dengan path di VPS
- Gunakan fitur **Sync CCD** di halaman pengaturan OVPN untuk sync ulang
