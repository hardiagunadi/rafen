<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createActiveUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $overrides));
}

it('shows new help center cards on index page', function () {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertSuccessful()
        ->assertSee('Panduan Per Role')
        ->assertSee('Peta Fitur Operasional')
        ->assertSee('FAQ Operasional');
});

it('shows role summary based on current user role', function () {
    $user = createActiveUser([
        'role' => 'teknisi',
    ]);

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertSuccessful()
        ->assertSee('Ringkasan akses untuk role Anda: TEKNISI')
        ->assertSee('Monitoring OLT (Polling Sekarang)');
});

it('opens each new help topic page', function (string $slug, string $expectedHeading) {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.topic', $slug))
        ->assertSuccessful()
        ->assertSee($expectedHeading);
})->with([
    ['panduan-role', 'Panduan Per Role'],
    ['fitur-operasional', 'Peta Fitur Operasional RAFEN'],
    ['faq', 'FAQ Operasional RAFEN'],
]);

it('returns not found for unknown help topic slug', function () {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.topic', 'topik-tidak-ada'))
        ->assertNotFound();
});
