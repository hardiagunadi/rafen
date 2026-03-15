<?php

use App\Models\CpeDevice;
use App\Models\PppUser;
use App\Models\User;
use App\Services\GenieAcsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function cpeTenantAdmin(): User
{
    return User::factory()->create([
        'role'                    => 'administrator',
        'subscription_status'     => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function cpePppUser(User $owner): PppUser
{
    return PppUser::factory()->forOwner($owner)->create([
        'username' => 'testuser@isp.id',
    ]);
}

// ---------------------------------------------------------------------------
// CPE Index
// ---------------------------------------------------------------------------

it('admin can access cpe index', function () {
    $admin = cpeTenantAdmin();

    $this->actingAs($admin)
        ->get(route('cpe.index'))
        ->assertSuccessful();
});

it('unauthenticated user cannot access cpe index', function () {
    $this->get(route('cpe.index'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// CPE Show (AJAX info)
// ---------------------------------------------------------------------------

it('returns not linked when no cpe device exists', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    $this->actingAs($admin)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertOk()
        ->assertJson(['linked' => false]);
});

it('returns linked true when cpe device exists', function () {
    $admin     = cpeTenantAdmin();
    $pppUser   = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'manufacturer'       => 'CMDC',
        'model'              => 'H3-2S XPON',
        'status'             => 'online',
    ]);

    $this->actingAs($admin)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertOk()
        ->assertJson(['linked' => true])
        ->assertJsonPath('device.manufacturer', 'CMDC');
});

// ---------------------------------------------------------------------------
// Sync
// ---------------------------------------------------------------------------

it('sync finds device and creates cpe record', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    $fakeDevice = [
        '_id'                    => 'A861DF-H3-2S-CMDCA106B700',
        'InternetGatewayDevice'  => [
            'DeviceInfo' => [
                'Manufacturer'    => ['_value' => 'CMDC'],
                'ModelName'       => ['_value' => 'H3-2S XPON'],
                'SoftwareVersion' => ['_value' => 'V1.1.20P1T4'],
                'SerialNumber'    => ['_value' => 'CMDCA106B700'],
            ],
        ],
        '_lastInform' => now()->toIso8601String(),
    ];

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('findDeviceByUsername')
        ->once()
        ->with('testuser@isp.id')
        ->andReturn($fakeDevice);
    $mock->shouldReceive('detectParamProfile')
        ->once()
        ->andReturn('igd');
    $mock->shouldReceive('extractValue')
        ->andReturnUsing(function ($doc, $path) {
            $map = [
                'InternetGatewayDevice.DeviceInfo.SerialNumber'    => 'CMDCA106B700',
                'InternetGatewayDevice.DeviceInfo.Manufacturer'    => 'CMDC',
                'InternetGatewayDevice.DeviceInfo.ModelName'       => 'H3-2S XPON',
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion' => 'V1.1.20P1T4',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => 'TestWifi',
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username' => 'testuser@isp.id',
                'InternetGatewayDevice.DeviceInfo.UpTime' => '3600',
            ];
            return $map[$path] ?? null;
        });

    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('cpe.sync', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('cpe_devices', [
        'ppp_user_id'        => $pppUser->id,
        'genieacs_device_id' => 'A861DF-H3-2S-CMDCA106B700',
    ]);
});

it('sync returns 404 when device not found in genieacs', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('findDeviceByUsername')
        ->once()
        ->andReturn(null);

    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('cpe.sync', $pppUser->id))
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

// ---------------------------------------------------------------------------
// Reboot
// ---------------------------------------------------------------------------

it('admin can reboot a linked cpe device', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('rebootDevice')
        ->once()
        ->with('TEST-DEVICE-001')
        ->andReturn(['queued' => true, 'task_id' => 'abc123', 'status' => 202]);

    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('queued', true);
});

it('teknisi can reboot a linked cpe device', function () {
    $admin   = cpeTenantAdmin();
    $teknisi = User::factory()->create([
        'role'      => 'teknisi',
        'parent_id' => $admin->id,
    ]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('rebootDevice')->once()->andReturn(['queued' => false, 'task_id' => null, 'status' => 200]);
    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($teknisi)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('cs role cannot reboot cpe device', function () {
    $admin   = cpeTenantAdmin();
    $cs      = User::factory()->create(['role' => 'cs', 'parent_id' => $admin->id]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($cs)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update WiFi
// ---------------------------------------------------------------------------

it('admin can update wifi settings', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'param_profile'      => 'igd',
    ]);

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('setWifi')
        ->once()
        ->with('TEST-DEVICE-001', 'MyWifi', 'password123', 'igd')
        ->andReturn(['queued' => true, 'task_id' => 'xyz', 'status' => 202]);

    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid'     => 'MyWifi',
            'password' => 'password123',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('wifi update validates ssid max 32 chars', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid'     => str_repeat('A', 33),
            'password' => 'password123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ssid']);
});

it('wifi update validates password min 8 chars', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid'     => 'ValidSSID',
            'password' => 'short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

// ---------------------------------------------------------------------------
// Update PPPoE
// ---------------------------------------------------------------------------

it('admin can update pppoe credentials', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'param_profile'      => 'igd',
    ]);

    $mock = Mockery::mock(GenieAcsClient::class);
    $mock->shouldReceive('setPppoeCredentials')
        ->once()
        ->andReturn(['queued' => false, 'task_id' => null, 'status' => 200]);

    $this->app->instance(GenieAcsClient::class, $mock);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-pppoe', $pppUser->id), [
            'username' => 'user@isp.id',
            'password' => 'pppoepass',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

// ---------------------------------------------------------------------------
// Tenant Isolation
// ---------------------------------------------------------------------------

it('tenant admin cannot access cpe of another tenant', function () {
    $admin1  = cpeTenantAdmin();
    $admin2  = cpeTenantAdmin();
    $pppUser = cpePppUser($admin1);

    $this->actingAs($admin2)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

it('admin can unlink cpe device', function () {
    $admin   = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    $device  = CpeDevice::create([
        'ppp_user_id'        => $pppUser->id,
        'owner_id'           => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('cpe.destroy', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('cpe_devices', ['id' => $device->id]);
});
