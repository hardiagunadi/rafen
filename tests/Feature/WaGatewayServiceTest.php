<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('requires device token in tenant wa configuration', function () {
    $user = User::factory()->create();
    $settings = TenantSettings::getOrCreate($user->id);

    $settings->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => '   ',
        'wa_gateway_key' => 'master-key',
    ]);

    expect($settings->fresh()->hasWaConfigured())->toBeFalse()
        ->and(WaGatewayService::forTenant($settings->fresh()))->toBeNull();
});

it('fails fast when sending without device token', function () {
    Log::spy();
    Http::fake();

    $service = new WaGatewayService('https://gateway.example/wa', '', 'master-key');
    $sent = $service->sendMessage('081234567890', 'Tes tanpa token', ['event' => 'blast']);

    expect($sent)->toBeFalse();

    Http::assertNothingSent();
    $this->assertDatabaseHas('wa_blast_logs', [
        'event' => 'blast',
        'status' => 'failed',
        'phone' => '081234567890',
        'reason' => 'Token perangkat WA belum diisi.',
    ]);
});

it('marks gateway message status failed as failed log', function () {
    Log::spy();
    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'message' => 'Message processed',
            'data' => [
                'messages' => [
                    [
                        'status' => 'failed',
                        'ref_id' => 'ref-failed-001',
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $settings = TenantSettings::getOrCreate($user->id);
    $settings->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-001',
        'wa_gateway_key' => 'master-key',
        'wa_msg_randomize' => false,
        'wa_antispam_enabled' => false,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $sent = $service->sendMessage('081234567890', 'Tes status gagal', ['event' => 'invoice_created']);

    expect($sent)->toBeFalse();

    Http::assertSentCount(1);
    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $user->id,
        'event' => 'invoice_created',
        'status' => 'failed',
        'phone' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'reason' => 'Status gateway: failed',
        'ref_id' => 'ref-failed-001',
    ]);
});
