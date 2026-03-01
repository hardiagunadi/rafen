# Rafen — Laravel ISP Management System

## Stack
- **Laravel 12** (PHP 8.2+), **AdminLTE 3.15** (jeroennoten/laravel-adminlte)
- **MariaDB** database: `rafen`, user: `rafen`
- **FreeRADIUS 3** for PPP/Hotspot authentication
- **WireGuard VPN** for MikroTik connectivity
- **Pest** for testing, **Laravel Pint** for code style

## Architecture

### Multi-Tenant Model
```
Super Admin (is_super_admin=true)        — platform owner, sees everything
Tenant Admin (role=administrator, parent_id=NULL) — ISP admin, owns their data
Sub-User (parent_id=tenant_id)           — NOC/IT Support/Keuangan/Mitra
```

- **Ownership**: most models have `owner_id` FK → `users.id` (tenant admin's ID)
- **Sub-user access**: `User::effectiveOwnerId()` returns `parent_id ?? id`, so sub-users transparently access their tenant's data
- **Scope pattern**: every model has `scopeAccessibleBy(Builder $query, User $user)` — always use this for queries

### User Roles
| Role | Access |
|------|--------|
| `is_super_admin=true` | Full platform access |
| `role=administrator` (parent_id=NULL) | Tenant admin — own data only |
| `role=noc/it_support/keuangan/mitra` (parent_id set) | Sub-user — inherits tenant's data access |

Key User methods:
- `isSuperAdmin()` — check via `is_super_admin` boolean (NOT role field)
- `isAdmin()` — role === 'administrator'
- `isSubUser()` — parent_id !== null
- `effectiveOwnerId()` — parent_id ?? id
- `canAccessApp()` — checks subscription; sub-users inherit from parent

### Subscription / Billing
- Tenant admins need active subscription or trial to access app
- Middleware: `EnsureSubscriptionActive` blocks expired tenants
- Sub-users inherit parent's subscription status via `canAccessApp()`
- Invoices auto-generated monthly via `billing:generate-invoices` artisan command

## Key Models & Files

### Models (`app/Models/`)
| Model | Table | Notes |
|-------|-------|-------|
| User | users | Multi-tenant users, parent_id for sub-users |
| PppUser | ppp_users | PPP customers, synced to FreeRADIUS |
| PppProfile | ppp_profiles | PPP speed profiles |
| HotspotUser | hotspot_users | Hotspot customers |
| HotspotProfile | hotspot_profiles | Hotspot profiles |
| MikrotikConnection | mikrotik_connections | Router connections |
| ProfileGroup | profile_groups | IP pool / queue groups |
| BandwidthProfile | bandwidth_profiles | Upload/download limits |
| Invoice | invoices | Monthly billing invoices |
| RadiusAccount | radius_accounts | RADIUS NAS clients |
| Voucher | vouchers | Hotspot vouchers |
| ActivityLog | activity_logs | Action audit log |
| LoginLog | login_logs | User login history |
| TenantSettings | tenant_settings | Per-tenant config (company, NPWP, etc.) |
| BankAccount | bank_accounts | Tenant bank accounts for invoice |
| Subscription | subscriptions | Tenant subscription records |

### Controllers (`app/Http/Controllers/`)
All controllers use `abort(403)` for unauthorized access. Ownership check pattern:
```php
if (! $user->isSuperAdmin() && $model->owner_id !== $user->effectiveOwnerId()) {
    abort(403);
}
```

### Services (`app/Services/`)
- `RadiusClientsSynchronizer` — sync MikroTik connections to FreeRADIUS clients.conf
- `WgPeerSynchronizer` — manage WireGuard peers for tenant VPN
- `MikrotikApiClient` — RouterOS API communication
- `HotspotRadiusSynchronizer` — sync hotspot users to RADIUS

### Traits (`app/Traits/`)
- `LogsActivity` — use in controllers to log actions to `activity_logs`

## FreeRADIUS Integration
- Config: `/etc/freeradius/3.0/`
- Clients file: `/etc/freeradius/3.0/clients.d/laravel.conf`
- **Must use `restart` not `reload`** — HUP doesn't reload clients.d/
- PPP user sync: `status_akun = 'enable'` → radcheck with `Cleartext-Password`
- `RADIUS_RELOAD_COMMAND="sudo systemctl restart freeradius"` in .env

## Development Commands
```bash
# Start dev server (all services)
composer dev

# Run tests
composer test

# Code formatting
./vendor/bin/pint

# Artisan commands of note
php artisan billing:generate-invoices
php artisan billing:reset-status
php artisan migrate
```

## Database Conventions
- All tables use `owner_id` (nullable FK → users.id) for tenant ownership
- `parent_id` in users table for sub-user hierarchy
- Soft deletes NOT used (hard delete)
- Status fields use Indonesian: `status_akun` (enable/disable/isolir), `status_bayar` (sudah_bayar/belum_bayar)

## View / Frontend
- **AdminLTE 3** layout: `resources/views/layouts/admin.blade.php`
- Datatables (server-side): `/datatable` routes return JSON for each resource
- Bulk actions via checkbox + AJAX
- Blade components in `resources/views/` — one folder per resource

## Common Patterns

### Accessible scope (always use this for listing):
```php
Model::query()->accessibleBy($user)->get();
```

### Ownership check in controller:
```php
if (! $user->isSuperAdmin() && $model->owner_id !== $user->effectiveOwnerId()) {
    abort(403);
}
```

### Sub-user visibility:
```php
// Show items owned by tenant + all sub-users
$visibleUserIds = [$user->id, ...$user->subUsers()->pluck('id')->all()];
```

### Logging activity:
```php
use App\Traits\LogsActivity;
$this->logActivity('created', 'ModelName', $model->id, $model->name, $ownerId);
```
