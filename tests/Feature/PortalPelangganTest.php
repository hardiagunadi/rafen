<?php

use App\Models\Invoice;
use App\Models\PortalSession;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function portalTenant(): User
{
    $user = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    TenantSettings::getOrCreate($user->id);

    return $user;
}

function makePppUser(int $ownerId, array $attrs = []): PppUser
{
    return PppUser::create(array_merge([
        'owner_id' => $ownerId,
        'username' => 'usertest_'.Str::random(6),
        'ppp_password' => 'pass123',
        'nomor_hp' => '6281234567890',
        'customer_name' => 'Budi',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'password_clientarea' => 'clientpass',
        'tipe_service' => 'pppoe',
    ], $attrs));
}

function makePortalSession(int $pppUserId): string
{
    $token = Str::random(64);
    PortalSession::create([
        'ppp_user_id' => $pppUserId,
        'token' => $token,
        'ip_address' => '127.0.0.1',
        'last_activity_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    return $token;
}

// ── Login page ─────────────────────────────────────────────────────────────────

it('shows portal login page', function () {
    $this->get(route('portal.login'))->assertOk()->assertSee('Login Portal Pelanggan');
});

// ── Login: plain text password ─────────────────────────────────────────────────

it('can login with plain text password_clientarea', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, [
        'nomor_hp' => '6281000000001',
        'password_clientarea' => 'mysecret',
    ]);

    $this->post(route('portal.login.post'), [
        'nomor_hp' => '081000000001', // with leading 0 — normalizes to 6281000000001
        'password' => 'mysecret',
    ])->assertRedirect(route('portal.dashboard'));

    expect(PortalSession::where('ppp_user_id', $ppp->id)->exists())->toBeTrue();
});

// ── Login: hashed password ─────────────────────────────────────────────────────

it('can login with hashed password_clientarea', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, [
        'nomor_hp' => '6281000000002',
        'password_clientarea' => Hash::make('hashed_pass'),
    ]);

    $this->post(route('portal.login.post'), [
        'nomor_hp' => '628' . '1000000002',
        'password' => 'hashed_pass',
    ])->assertRedirect(route('portal.dashboard'));
});

// ── Login: wrong password ─────────────────────────────────────────────────────

it('rejects login with wrong password', function () {
    $tenant = portalTenant();
    makePppUser($tenant->id, [
        'nomor_hp' => '6281000000003',
        'password_clientarea' => 'correct',
    ]);

    $this->post(route('portal.login.post'), [
        'nomor_hp' => '6281000000003',
        'password' => 'wrong',
    ])->assertRedirect()->assertSessionHasErrors('password');

    expect(PortalSession::count())->toBe(0);
});

// ── Login: unknown phone ───────────────────────────────────────────────────────

it('rejects login with unknown phone number', function () {
    $this->post(route('portal.login.post'), [
        'nomor_hp' => '6289999999999',
        'password' => 'anything',
    ])->assertRedirect()->assertSessionHasErrors('nomor_hp');
});

// ── Middleware: unauthenticated redirected ─────────────────────────────────────

it('redirects unauthenticated portal request to login', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    $this->get(route('portal.invoices'))->assertRedirect(route('portal.login'));
    $this->get(route('portal.account'))->assertRedirect(route('portal.login'));
});

// ── Middleware: expired session ────────────────────────────────────────────────

it('redirects when portal session is expired', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id);
    $token = Str::random(64);
    PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => $token,
        'expires_at' => now()->subHour(), // expired
        'last_activity_at' => now()->subHour(),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

it('shows dashboard for authenticated portal user', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, ['customer_name' => 'Budi Santoso']);
    $token = makePortalSession($ppp->id);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Budi Santoso');
});

// ── Invoices ──────────────────────────────────────────────────────────────────

it('shows invoice list for authenticated portal user', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id);
    $token = makePortalSession($ppp->id);

    Invoice::create([
        'invoice_number' => 'INV-202501001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'total' => 150000,
        'status' => 'belum_bayar',
        'due_date' => now()->addDays(10),
        'payment_token' => Str::random(32),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.invoices'))
        ->assertOk()
        ->assertSee('INV-202501001');
});

// ── Change Password ───────────────────────────────────────────────────────────

it('can change portal password', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'oldpassword']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password'), [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    // New password should be hashed and valid
    expect(Hash::check('newpassword123', $ppp->fresh()->password_clientarea))->toBeTrue();
});

it('rejects change password when current password is wrong', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'correctpass']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password'), [
            'current_password' => 'wrongpass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

it('rejects change password when confirmation does not match', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'pass']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password'), [
            'current_password' => 'pass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'different',
        ])
        ->assertStatus(422);
});

// ── Logout ────────────────────────────────────────────────────────────────────

it('logout deletes portal session', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id);
    $token = makePortalSession($ppp->id);

    $this->withCookie('portal_session', $token)
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    expect(PortalSession::where('token', $token)->exists())->toBeFalse();
});

// ── PortalSession model ────────────────────────────────────────────────────────

it('PortalSession isExpired returns true for expired sessions', function () {
    $tenant = portalTenant();
    $ppp = makePppUser($tenant->id);

    $expired = PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => Str::random(64),
        'expires_at' => now()->subMinute(),
        'last_activity_at' => now()->subMinute(),
    ]);

    $active = PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
        'last_activity_at' => now(),
    ]);

    expect($expired->isExpired())->toBeTrue();
    expect($active->isExpired())->toBeFalse();
});

// ── Multi-tenant: phone collision shows picker ─────────────────────────────────

it('shows tenant picker when same phone exists in multiple tenants', function () {
    $tenant1 = portalTenant();
    $tenant2 = portalTenant();
    $phone = '6281999888777';

    makePppUser($tenant1->id, ['nomor_hp' => $phone, 'password_clientarea' => 'pass1']);
    makePppUser($tenant2->id, ['nomor_hp' => $phone, 'password_clientarea' => 'pass2']);

    $res = $this->post(route('portal.login.post'), [
        'nomor_hp' => $phone,
        'password' => 'pass1',
    ]);

    // Should show picker (200 with tenant picker view), not redirect
    $res->assertOk()->assertSee('Pilih ISP');
});
