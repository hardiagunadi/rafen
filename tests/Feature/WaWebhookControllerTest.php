<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

it('accepts standard webhook message endpoint and stores sender with status', function () {
    $response = $this->postJson('/webhook/message', [
        'session' => 'device-01',
        'from' => '6281211112222@s.whatsapp.net',
        'message' => 'Halo dari pelanggan',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'message',
        'session_id' => 'device-01',
        'sender' => '6281211112222',
        'message' => 'Halo dari pelanggan',
        'status' => 'received',
    ]);
});

it('accepts wa-prefixed webhook message endpoint', function () {
    $response = $this->postJson('/webhook/wa/message', [
        'session' => 'device-02',
        'sender' => '6281399990000@s.whatsapp.net',
        'message' => [
            'text' => 'Tes format array',
        ],
        'status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'message',
        'session_id' => 'device-02',
        'sender' => '6281399990000',
        'message' => 'Tes format array',
        'status' => 'received',
    ]);
});

it('accepts standard webhook session endpoint', function () {
    $response = $this->postJson('/webhook/session', [
        'session_id' => 'device-03',
        'status' => 'online',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'session',
        'session_id' => 'device-03',
        'status' => 'online',
    ]);
});
