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

it('accepts wa-prefixed auto-reply endpoint', function () {
    $response = $this->postJson('/webhook/wa/auto-reply', [
        'session' => 'device-02',
        'from' => '6281388887777@s.whatsapp.net',
        'message' => 'Tes auto reply',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'auto_reply',
        'session_id' => 'device-02',
        'sender' => '6281388887777',
        'message' => 'Tes auto reply',
        'status' => 'received',
    ]);
});

it('accepts wa-prefixed status endpoint', function () {
    $response = $this->postJson('/webhook/wa/status', [
        'session' => 'device-04',
        'message_id' => 'BAE5F123',
        'message_status' => 'READ',
        'tracking_url' => '/message/status?session=device-04&id=BAE5F123',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'status',
        'session_id' => 'device-04',
        'message' => 'BAE5F123',
        'status' => 'READ',
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
