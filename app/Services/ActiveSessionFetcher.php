<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use Carbon\Carbon;

class ActiveSessionFetcher
{
    public function __construct(private MikrotikApiClient $client) {}

    /**
     * Sync active PPPoE sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     */
    public function syncPpp(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response = $this->client->command('/ppp/active');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        $this->upsertSessions($conn, 'pppoe', $sessions, function (array $row): array {
            return [
                'username'    => $row['name'] ?? '',
                'ipv4_address' => $row['address'] ?? null,
                'uptime'      => $row['uptime'] ?? null,
                'caller_id'   => $row['caller-id'] ?? null,
                'server_name' => null,
                'profile'     => $row['service'] ?? null,
            ];
        });

        return count($sessions);
    }

    /**
     * Sync active Hotspot sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     */
    public function syncHotspot(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response = $this->client->command('/ip/hotspot/active');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        $this->upsertSessions($conn, 'hotspot', $sessions, function (array $row): array {
            return [
                'username'    => $row['user'] ?? ($row['name'] ?? ''),
                'ipv4_address' => $row['address'] ?? null,
                'uptime'      => $row['uptime'] ?? null,
                'caller_id'   => $row['mac-address'] ?? null,
                'server_name' => $row['server'] ?? null,
                'profile'     => null,
            ];
        });

        return count($sessions);
    }

    /**
     * @param  array<int, array<string, string>>  $sessions
     * @param  callable(array<string, string>): array<string, mixed>  $mapper
     */
    private function upsertSessions(
        MikrotikConnection $conn,
        string $service,
        array $sessions,
        callable $mapper
    ): void {
        $now = Carbon::now()->toDateTimeString();
        $activeUsernames = [];

        foreach ($sessions as $row) {
            $mapped = $mapper($row);
            $username = $mapped['username'];

            if (empty($username)) {
                continue;
            }

            $activeUsernames[] = $username;

            RadiusAccount::updateOrCreate(
                [
                    'mikrotik_connection_id' => $conn->id,
                    'username'               => $username,
                    'service'                => $service,
                ],
                [
                    'ipv4_address' => $mapped['ipv4_address'],
                    'uptime'       => $mapped['uptime'],
                    'caller_id'    => $mapped['caller_id'],
                    'server_name'  => $mapped['server_name'],
                    'profile'      => $mapped['profile'],
                    'is_active'    => true,
                    'updated_at'   => $now,
                ]
            );
        }

        // Mark sessions no longer in MikroTik response as inactive
        RadiusAccount::where('mikrotik_connection_id', $conn->id)
            ->where('service', $service)
            ->where('is_active', true)
            ->when(! empty($activeUsernames), fn ($q) => $q->whereNotIn('username', $activeUsernames))
            ->update(['is_active' => false, 'updated_at' => $now]);
    }
}
