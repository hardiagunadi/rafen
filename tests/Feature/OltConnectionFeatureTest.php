<?php

use App\Models\OltConnection;
use App\Models\OltOnuOptic;
use App\Models\User;
use App\Services\HsgqSnmpCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('allows tenant admin to create hsgq olt connection', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($tenant)
        ->post(route('olt-connections.store'), [
            'vendor' => 'hsgq',
            'name' => 'OLT HSGQ Watu 01',
            'olt_model' => 'HSGQ-E04I',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_write_community' => 'private',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
            'is_active' => '1',
            'oid_serial' => '1.3.6.1.4.1.1.1',
            'oid_onu_name' => '1.3.6.1.4.1.1.2',
            'oid_rx_onu' => '1.3.6.1.4.1.1.3',
            'oid_tx_onu' => '1.3.6.1.4.1.1.4',
            'oid_rx_olt' => '1.3.6.1.4.1.1.5',
            'oid_tx_olt' => '1.3.6.1.4.1.1.6',
            'oid_distance' => '1.3.6.1.4.1.1.7',
            'oid_status' => '1.3.6.1.4.1.1.8',
        ]);

    $oltConnection = OltConnection::query()->first();

    expect($oltConnection)->not->toBeNull();

    $response->assertRedirect(route('olt-connections.show', $oltConnection));

    expect($oltConnection->owner_id)->toBe($tenant->id)
        ->and($oltConnection->vendor)->toBe('hsgq')
        ->and($oltConnection->name)->toBe('OLT HSGQ Watu 01')
        ->and($oltConnection->olt_model)->toBe('HSGQ-E04I')
        ->and($oltConnection->snmp_community)->toBe('public')
        ->and($oltConnection->snmp_write_community)->toBe('private');
});

it('renders olt pages for tenant admin', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'OLT UI Test',
    ]);

    $this->actingAs($tenant)
        ->get(route('olt-connections.index'))
        ->assertSuccessful()
        ->assertSee('Monitoring OLT');

    $this->actingAs($tenant)
        ->get(route('olt-connections.create'))
        ->assertSuccessful()
        ->assertSee('Tambah Koneksi OLT HSGQ')
        ->assertSee('Model OLT HSGQ')
        ->assertSee('SNMP Read Community')
        ->assertSee('SNMP Write Community')
        ->assertSee('OID MAC / Identifier ONU')
        ->assertSee('OID Distance (m)');

    $this->actingAs($tenant)
        ->get(route('olt-connections.show', $connection))
        ->assertSuccessful()
        ->assertSee('OLT UI Test');

    $this->actingAs($tenant)
        ->get(route('olt-connections.edit', $connection))
        ->assertSuccessful()
        ->assertSee('Edit Koneksi OLT HSGQ')
        ->assertSee('Model OLT HSGQ')
        ->assertSee('SNMP Read Community')
        ->assertSee('SNMP Write Community')
        ->assertSee('OID MAC / Identifier ONU')
        ->assertSee('OID Distance (m)');
});

it('parses polling progress from running poll message', function () {
    $connection = OltConnection::factory()->make([
        'last_poll_message' => '[RUNNING] 42% Membaca OID status (3/7)',
    ]);

    expect($connection->isPollingInProgress())->toBeTrue()
        ->and($connection->pollingProgressPercent())->toBe(42)
        ->and($connection->pollingDisplayMessage())->toBe('Membaca OID status (3/7)');
});

it('auto detects oid mapping based on selected olt model', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    app()->instance(HsgqSnmpCollector::class, new class extends HsgqSnmpCollector
    {
        /**
         * @param  array<string, mixed>  $connectionConfig
         * @return array{
         *   model: string,
         *   oids: array<string, string>,
         *   probe: array<string, array{oid: string, sample_count: int, detected: bool}>,
         *   detected_fields: int
         * }
         */
        public function detectMappingFromModel(array $connectionConfig): array
        {
            return [
                'model' => (string) $connectionConfig['olt_model'],
                'oids' => [
                    'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                    'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                    'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                    'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                    'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                    'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                    'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                ],
                'probe' => [
                    'oid_serial' => [
                        'oid' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                        'sample_count' => 10,
                        'detected' => true,
                    ],
                ],
                'detected_fields' => 7,
            ];
        }
    });

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-oid'), [
            'vendor' => 'hsgq',
            'olt_model' => 'HSGQ GPON 8 PON',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.model', 'HSGQ GPON 8 PON')
        ->assertJsonPath('data.detected_fields', 7)
        ->assertJsonPath('data.oids.oid_serial', '1.3.6.1.4.1.5875.800.3.1.1.1.1.2');
});

it('auto detects oid mapping for hsgq e04i epon profile from configured oids', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    Process::fake(function ($process) {
        $command = $process->command;

        return match (true) {
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.7') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.7.16777473 = Hex-STRING: D0 60 8C BC BD C3'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.2') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.2.16777473 = STRING: "ONU02/16_TURMIN_PSW"'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.3.1.4') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.3.1.4.16777473.0.0 = INTEGER: -2017'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.3.1.5') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.3.1.5.16777473.0.0 = INTEGER: 265'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.2.4.1.12') => Process::result(
                '.1.3.6.1.4.1.50224.3.2.4.1.12.16777472 = INTEGER: -2619'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.2.4.1.8') => Process::result(
                '.1.3.6.1.4.1.50224.3.2.4.1.8.16777472 = INTEGER: 6201'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.15') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.15.16777473 = INTEGER: 3798'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.8') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.8.16777473 = INTEGER: 1'
            ),
            default => Process::result('', 'Unexpected SNMP command in test.', 1),
        };
    });

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-oid'), [
            'vendor' => 'hsgq',
            'olt_model' => 'HSGQ-E04I (EPON)',
            'host' => '103.38.104.218',
            'snmp_port' => 16162,
            'snmp_version' => '2c',
            'snmp_community' => 'tmdusro',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.model', 'HSGQ-E04I (EPON)')
        ->assertJsonPath('data.detected_fields', 8)
        ->assertJsonPath('data.oids.oid_serial', '1.3.6.1.4.1.50224.3.3.2.1.7')
        ->assertJsonPath('data.oids.oid_onu_name', '1.3.6.1.4.1.50224.3.3.2.1.2')
        ->assertJsonPath('data.oids.oid_rx_onu', '1.3.6.1.4.1.50224.3.3.3.1.4')
        ->assertJsonPath('data.oids.oid_tx_onu', '1.3.6.1.4.1.50224.3.3.3.1.5')
        ->assertJsonPath('data.oids.oid_rx_olt', '1.3.6.1.4.1.50224.3.2.4.1.12')
        ->assertJsonPath('data.oids.oid_tx_olt', '1.3.6.1.4.1.50224.3.2.4.1.8')
        ->assertJsonPath('data.oids.oid_distance', '1.3.6.1.4.1.50224.3.3.2.1.15')
        ->assertJsonPath('data.oids.oid_status', '1.3.6.1.4.1.50224.3.3.2.1.8');
});

it('normalizes hsgq e04i optical values during polling', function () {
    $connection = OltConnection::factory()->create([
        'vendor' => 'hsgq',
        'olt_model' => 'HSGQ-E04I (EPON)',
        'host' => '103.38.104.218',
        'snmp_port' => 16162,
        'snmp_version' => '2c',
        'snmp_community' => 'tmdusro',
        'snmp_timeout' => 5,
        'snmp_retries' => 1,
        'oid_serial' => '1.3.6.1.4.1.50224.3.3.2.1.7',
        'oid_onu_name' => '1.3.6.1.4.1.50224.3.3.2.1.2',
        'oid_rx_onu' => '1.3.6.1.4.1.50224.3.3.3.1.4',
        'oid_tx_onu' => '1.3.6.1.4.1.50224.3.3.3.1.5',
        'oid_rx_olt' => '1.3.6.1.4.1.50224.3.2.4.1.12',
        'oid_tx_olt' => '1.3.6.1.4.1.50224.3.2.4.1.8',
        'oid_distance' => '1.3.6.1.4.1.50224.3.3.2.1.15',
        'oid_status' => '1.3.6.1.4.1.50224.3.3.2.1.8',
    ]);

    Process::fake(function ($process) {
        $command = $process->command;

        return match (true) {
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.7') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.7.16777473 = Hex-STRING: D0 60 8C BC BD C3'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.2') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.2.16777473 = STRING: "ONU02/16_TURMIN_PSW"'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.3.1.4') => Process::result(
                ".1.3.6.1.4.1.50224.3.3.3.1.4.16777472.65535.65535 = INTEGER: -2147483648\n".
                '.1.3.6.1.4.1.50224.3.3.3.1.4.16777473.0.0 = INTEGER: -2017'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.3.1.5') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.3.1.5.16777473.0.0 = INTEGER: 265'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.2.4.1.12') => Process::result(
                '.1.3.6.1.4.1.50224.3.2.4.1.12.16777472 = INTEGER: -2619'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.2.4.1.8') => Process::result(
                '.1.3.6.1.4.1.50224.3.2.4.1.8.16777472 = INTEGER: 6201'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.15') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.15.16777473 = INTEGER: 3798'
            ),
            str_contains($command, '.1.3.6.1.4.1.50224.3.3.2.1.8') => Process::result(
                '.1.3.6.1.4.1.50224.3.3.2.1.8.16777473 = INTEGER: 1'
            ),
            default => Process::result('', 'Unexpected SNMP command in test.', 1),
        };
    });

    $rows = app(HsgqSnmpCollector::class)->collect($connection);

    expect($rows)->toHaveCount(1)
        ->and((string) $rows[0]['onu_index'])->toBe('16777473')
        ->and($rows[0]['pon_interface'])->toBe('PON1')
        ->and($rows[0]['onu_number'])->toBe('1')
        ->and($rows[0]['serial_number'])->toBe('D0 60 8C BC BD C3')
        ->and($rows[0]['onu_name'])->toBe('ONU02/16_TURMIN_PSW')
        ->and($rows[0]['distance_m'])->toBe(3798)
        ->and($rows[0]['rx_onu_dbm'])->toBe(-20.17)
        ->and($rows[0]['tx_onu_dbm'])->toBe(2.65)
        ->and($rows[0]['rx_olt_dbm'])->toBe(-26.19)
        ->and($rows[0]['tx_olt_dbm'])->toBe(6.2)
        ->and($rows[0]['status'])->toBe('online');
});

it('uses process timeout derived from snmp timeout and retries', function () {
    $connection = OltConnection::factory()->create([
        'snmp_timeout' => 5,
        'snmp_retries' => 1,
        'oid_serial' => '1.3.6.1.4.1.1.1',
        'oid_onu_name' => null,
        'oid_rx_onu' => null,
        'oid_tx_onu' => null,
        'oid_rx_olt' => null,
        'oid_tx_olt' => null,
        'oid_distance' => null,
        'oid_status' => null,
    ]);

    Process::fake(fn () => Process::result(
        '.1.3.6.1.4.1.1.1.16777473 = STRING: "D0 60 8C BC BD C3"'
    ));

    $rows = app(HsgqSnmpCollector::class)->collect($connection);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['serial_number'])->toBe('D0 60 8C BC BD C3');

    Process::assertRan(function ($process) {
        return str_contains($process->command, '.1.3.6.1.4.1.1.1')
            && $process->timeout === 13;
    });
});

it('auto detects olt model from snmp metadata', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    app()->instance(HsgqSnmpCollector::class, new class extends HsgqSnmpCollector
    {
        /**
         * @param  array<string, mixed>  $connectionConfig
         * @return array{
         *   matched_model: string|null,
         *   sys_descr: string|null,
         *   sys_object_id: string|null,
         *   candidate_models: array<int, string>
         * }
         */
        public function detectModelFromSnmp(array $connectionConfig): array
        {
            return [
                'matched_model' => 'HSGQ-E04I (EPON)',
                'sys_descr' => 'HSGQ-E04I',
                'sys_object_id' => '1.3.6.1.4.1.5875.800',
                'candidate_models' => ['HSGQ-E04I (EPON)'],
            ];
        }
    });

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-model'), [
            'vendor' => 'hsgq',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.matched_model', 'HSGQ-E04I (EPON)')
        ->assertJsonPath('data.sys_descr', 'HSGQ-E04I');
});

it('returns a clear validation message when detect model misses snmp community', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-model'), [
            'vendor' => 'hsgq',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'SNMP Community wajib diisi sebelum deteksi model.')
        ->assertJsonPath('errors.snmp_community.0', 'SNMP Community wajib diisi sebelum deteksi model.');
});

it('returns a clear validation message when auto detect oid misses snmp community', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-oid'), [
            'vendor' => 'hsgq',
            'olt_model' => 'HSGQ-E04I (EPON)',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'SNMP Community wajib diisi sebelum auto detect OID.')
        ->assertJsonPath('errors.snmp_community.0', 'SNMP Community wajib diisi sebelum auto detect OID.');
});

it('returns a profile mismatch message when snmp is reachable but no configured oid responds', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    Process::fake(function ($process) {
        $command = $process->command;

        return match (true) {
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.2'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.3'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.4'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.5'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.6'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.7'),
            str_contains($command, '.1.3.6.1.4.1.5875.800.3.1.1.1.1.8') => Process::result(
                'No Such Object available on this agent at this OID'
            ),
            str_contains($command, '.1.3.6.1.2.1.1.1.0') => Process::result(
                '.1.3.6.1.2.1.1.1.0 = STRING: "HSGQ-E04I"'
            ),
            str_contains($command, '.1.3.6.1.2.1.1.2.0') => Process::result(
                '.1.3.6.1.2.1.1.2.0 = OID: .1.3.6.1.4.1.50224.3.1.1'
            ),
            default => Process::result('', 'Unexpected SNMP command in test.', 1),
        };
    });

    $this->actingAs($tenant)
        ->postJson(route('olt-connections.auto-detect-oid'), [
            'vendor' => 'hsgq',
            'olt_model' => 'HSGQ GPON 8 PON',
            'host' => '103.38.104.218',
            'snmp_port' => 16162,
            'snmp_version' => '2c',
            'snmp_community' => 'tmdusro',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'SNMP terhubung, tetapi profil OID untuk model "HSGQ GPON 8 PON" tidak cocok dengan perangkat ini. Perangkat terdeteksi sebagai "HSGQ-E04I (EPON)".'
        );
});

it('forbids teknisi to auto detect oid mapping', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($teknisi)
        ->postJson(route('olt-connections.auto-detect-oid'), [
            'vendor' => 'hsgq',
            'olt_model' => 'HSGQ GPON 8 PON',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($teknisi)
        ->postJson(route('olt-connections.auto-detect-model'), [
            'vendor' => 'hsgq',
            'host' => '10.10.10.1',
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
        ])
        ->assertForbidden();
});

it('forbids teknisi to create and poll olt connection', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    $this->actingAs($teknisi)
        ->get(route('olt-connections.create'))
        ->assertForbidden();

    $this->actingAs($teknisi)
        ->post(route('olt-connections.store'), [])
        ->assertForbidden();

    $this->actingAs($teknisi)
        ->post(route('olt-connections.poll', $connection))
        ->assertForbidden();
});

it('forbids cross tenant access to olt connection', function () {
    $tenantA = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $tenantB = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenantB->id,
    ]);

    $this->actingAs($tenantA)
        ->get(route('olt-connections.show', $connection))
        ->assertForbidden();

    $this->actingAs($tenantA)
        ->post(route('olt-connections.poll', $connection))
        ->assertForbidden();
});

it('upserts onu optical data when polling succeeds', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    OltOnuOptic::factory()->create([
        'olt_connection_id' => $connection->id,
        'owner_id' => $tenant->id,
        'onu_index' => '1001.1',
        'serial_number' => 'HSGQOLD000001',
        'distance_m' => 1240,
        'rx_onu_dbm' => -25.10,
        'tx_onu_dbm' => 1.20,
        'rx_olt_dbm' => -24.50,
        'tx_olt_dbm' => 2.10,
        'status' => 'offline',
    ]);

    app()->instance(HsgqSnmpCollector::class, new class extends HsgqSnmpCollector
    {
        public function collect(OltConnection $oltConnection, ?callable $progressReporter = null): array
        {
            return [
                [
                    'onu_index' => '1001.1',
                    'pon_interface' => '1001',
                    'onu_number' => '1',
                    'serial_number' => 'HSGQNEW000001',
                    'onu_name' => 'ONU PELANGGAN A',
                    'distance_m' => 3798,
                    'rx_onu_dbm' => -18.50,
                    'tx_onu_dbm' => 2.40,
                    'rx_olt_dbm' => -19.20,
                    'tx_olt_dbm' => 3.10,
                    'status' => 'online',
                    'raw_payload' => [
                        'rx_onu' => '-18.50',
                        'tx_onu' => '2.40',
                        'rx_olt' => '-19.20',
                        'tx_olt' => '3.10',
                        'distance' => '3798',
                        'status' => 'online',
                    ],
                ],
            ];
        }
    });

    $this->actingAs($tenant)
        ->post(route('olt-connections.poll', $connection))
        ->assertRedirect(route('olt-connections.show', $connection))
        ->assertSessionHas('status');

    $connection->refresh();
    $onuOptic = OltOnuOptic::query()
        ->where('olt_connection_id', $connection->id)
        ->where('onu_index', '1001.1')
        ->firstOrFail();

    expect(OltOnuOptic::query()->where('olt_connection_id', $connection->id)->count())->toBe(1)
        ->and($onuOptic->serial_number)->toBe('HSGQNEW000001')
        ->and($onuOptic->distance_m)->toBe(3798)
        ->and((float) $onuOptic->rx_onu_dbm)->toBe(-18.5)
        ->and((float) $onuOptic->tx_olt_dbm)->toBe(3.1)
        ->and($onuOptic->status)->toBe('online')
        ->and($connection->last_poll_success)->toBeTrue()
        ->and((string) $connection->last_poll_message)->toContain('ONU terdeteksi: 1');
});

it('renders the onu optics datatable on show page', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    $this->actingAs($tenant)
        ->get(route('olt-connections.show', [
            'olt_connection' => $connection,
            'port_id' => 'PON1',
            'status' => 'online',
            'search' => '1/1',
        ]))
        ->assertSuccessful()
        ->assertSee('Semua Port ID')
        ->assertSee('Total ONU')
        ->assertSee('onu-optics-table', false)
        ->assertDontSee('<th>Index</th>', false)
        ->assertDontSee('<th>Rx OLT</th>', false)
        ->assertSee(route('olt-connections.datatable', $connection), false);
});

it('filters onu optics by selected port id on datatable endpoint', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    OltOnuOptic::factory()->create([
        'olt_connection_id' => $connection->id,
        'owner_id' => $tenant->id,
        'pon_interface' => 'PON1',
        'onu_number' => '1',
        'serial_number' => 'D0 60 8C BC BD C3',
        'onu_name' => 'ONU PORT 1',
        'status' => 'online',
    ]);

    OltOnuOptic::factory()->create([
        'olt_connection_id' => $connection->id,
        'owner_id' => $tenant->id,
        'pon_interface' => 'PON2',
        'onu_number' => '3',
        'onu_name' => 'ONU PORT 2',
        'status' => 'offline',
    ]);

    $response = $this->actingAs($tenant)
        ->getJson(route('olt-connections.datatable', [
            'oltConnection' => $connection,
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'port_id' => 'PON1',
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('draw', 1)
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.pon_interface', 'PON1')
        ->assertJsonPath('data.0.onu_id', '1/1')
        ->assertJsonPath('data.0.serial_number', 'd0:60:8c:bc:bd:c3')
        ->assertJsonPath('data.0.onu_name', 'ONU PORT 1');
});

it('filters onu optics by selected status and search on datatable endpoint', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    OltOnuOptic::factory()->create([
        'olt_connection_id' => $connection->id,
        'owner_id' => $tenant->id,
        'pon_interface' => 'PON1',
        'onu_number' => '1',
        'onu_name' => 'ONU ONLINE ONLY',
        'status' => 'online',
    ]);

    OltOnuOptic::factory()->create([
        'olt_connection_id' => $connection->id,
        'owner_id' => $tenant->id,
        'pon_interface' => 'PON1',
        'onu_number' => '2',
        'onu_name' => 'ONU OFFLINE ONLY',
        'status' => 'offline',
    ]);

    $response = $this->actingAs($tenant)
        ->getJson(route('olt-connections.datatable', [
            'oltConnection' => $connection,
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'online',
            'search' => ['value' => '1/1'],
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.onu_id', '1/1')
        ->assertJsonPath('data.0.status', 'ONLINE')
        ->assertJsonPath('data.0.onu_name', 'ONU ONLINE ONLY');
});

it('stores polling failure message when collector throws exception', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    app()->instance(HsgqSnmpCollector::class, new class extends HsgqSnmpCollector
    {
        public function collect(OltConnection $oltConnection, ?callable $progressReporter = null): array
        {
            throw new \RuntimeException('SNMP timeout ke OLT HSGQ.');
        }
    });

    $this->actingAs($tenant)
        ->post(route('olt-connections.poll', $connection))
        ->assertRedirect(route('olt-connections.show', $connection))
        ->assertSessionHas('error');

    $connection->refresh();

    expect($connection->last_poll_success)->toBeFalse()
        ->and((string) $connection->last_poll_message)->toContain('SNMP timeout ke OLT.');
});

it('normalizes raw process timeout message when polling fails', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $connection = OltConnection::factory()->create([
        'owner_id' => $tenant->id,
        'snmp_timeout' => 5,
        'snmp_retries' => 1,
    ]);

    app()->instance(HsgqSnmpCollector::class, new class extends HsgqSnmpCollector
    {
        public function collect(OltConnection $oltConnection, ?callable $progressReporter = null): array
        {
            throw new \RuntimeException(
                'The process "snmpwalk -On -v2c -c \'public\' -t 5 -r 1 \'10.10.10.1:161\' \'.1.3.6.1.4.1.1.1\'" exceeded the timeout of 8 seconds.'
            );
        }
    });

    $this->actingAs($tenant)
        ->post(route('olt-connections.poll', $connection))
        ->assertRedirect(route('olt-connections.show', $connection))
        ->assertSessionHas('error');

    $connection->refresh();

    expect($connection->last_poll_success)->toBeFalse()
        ->and((string) $connection->last_poll_message)->toContain('SNMP timeout ke OLT.')
        ->and((string) $connection->last_poll_message)->not->toContain('exceeded the timeout');
});
