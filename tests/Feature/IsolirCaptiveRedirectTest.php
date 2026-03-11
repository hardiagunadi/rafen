<?php

use App\Models\MikrotikConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects captive requests from mikrotik host to tenant isolir page', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.10',
        'is_active' => true,
        'is_online' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_HOST' => 'example.org',
    ])->get('/login');

    $response->assertRedirect(route('isolir.show', ['userId' => $owner->id]));
});

it('does not redirect webhook endpoint even when request comes from mikrotik host', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.11',
        'is_active' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.11',
        'HTTP_HOST' => 'example.org',
    ])->get('/webhook/wa');

    $response->assertSuccessful();
});
