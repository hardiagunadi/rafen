# FreeRADIUS Config Backup

Folder ini berisi backup file konfigurasi FreeRADIUS yang sudah dimodifikasi untuk Rafen.
Copy ke server baru setelah install `freeradius` dan `freeradius-mysql`.

## Instalasi di Server Baru

```bash
# 1. Install paket
apt install freeradius freeradius-mysql

# 2. Copy semua file dari folder ini
cp policy.d/filter          /etc/freeradius/3.0/policy.d/filter
cp policy.d/strip_pppoe_prefix /etc/freeradius/3.0/policy.d/strip_pppoe_prefix
cp mods-available/sql       /etc/freeradius/3.0/mods-available/sql
cp sites-available/default  /etc/freeradius/3.0/sites-available/default
cp clients.d/laravel.conf   /etc/freeradius/3.0/clients.d/laravel.conf

# 3. Aktifkan module sql (jika belum)
ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# 4. Set ownership
chown freerad:freerad /etc/freeradius/3.0/policy.d/strip_pppoe_prefix
chown freerad:freerad /etc/freeradius/3.0/clients.d/laravel.conf

# 5. Restart
systemctl restart freeradius
systemctl enable freeradius
```

## Sudoers untuk www-data

```bash
visudo
# Tambahkan:
www-data ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius
```

## File yang Dimodifikasi & Perubahan

### `policy.d/filter`
- Dinonaktifkan: validasi "realm harus mengandung titik" (`Realm does not have at least one dot separator`)
- Alasan: username hotspot/PPPoE seperti `RK2R@1189` valid tapi tidak punya TLD

### `policy.d/strip_pppoe_prefix`
- File baru: stripping prefix `pppoe-` dari User-Name
- MikroTik mengirim `pppoe-username@domain.com`, RADIUS mencari `username@domain.com`
- Hasil disimpan ke `Stripped-User-Name`

### `mods-available/sql`
- `sql_user_name` diset ke `Stripped-User-Name` (fallback ke `User-Name`)
- Agar lookup SQL menggunakan username tanpa prefix `pppoe-`

### `sites-available/default`
- Ditambahkan `strip_pppoe_prefix` di awal blok `authorize {}`

### `clients.d/laravel.conf`
- NAS clients yang didaftarkan oleh Rafen (di-generate otomatis via app)
- File ini akan di-overwrite oleh Rafen saat ada perubahan koneksi MikroTik
