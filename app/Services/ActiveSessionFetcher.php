<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActiveSessionFetcher
{
    public function __construct(private MikrotikApiClient $client) {}

    /**
     * Sync active PPPoE sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     * Throws RuntimeException if router is unreachable — caller must handle.
     */
    public function syncPpp(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response  = $this->client->command('/ppp/active/print');
        $ifResponse = $this->client->command('/interface/print');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        // Index interface stats by username extracted from interface name "<pppoe-username>"
        $ifStats = [];
        foreach ($ifResponse['data'] ?? [] as $iface) {
            $name = $iface['name'] ?? '';
            if (str_starts_with($name, '<pppoe-') && str_ends_with($name, '>')) {
                $username = substr($name, 7, -1); // strip "<pppoe-" and ">"
                $ifStats[$username] = [
                    'bytes_in'  => isset($iface['rx-byte']) ? (int) $iface['rx-byte'] : null,
                    'bytes_out' => isset($iface['tx-byte']) ? (int) $iface['tx-byte'] : null,
                ];
            }
        }

        $this->upsertSessions($conn, 'pppoe', $sessions, function (array $row) use ($ifStats): array {
            $username = $row['name'] ?? '';
            $stats    = $ifStats[$username] ?? [];
            return [
                'username'     => $username,
                'ipv4_address' => $row['address'] ?? null,
                'uptime'       => $row['uptime'] ?? null,
                'caller_id'    => $row['caller-id'] ?? null,
                'server_name'  => null,
                'profile'      => $row['service'] ?? null,
                'bytes_in'     => $stats['bytes_in'] ?? null,
                'bytes_out'    => $stats['bytes_out'] ?? null,
            ];
        });

        return count($sessions);
    }

    /**
     * Sync active Hotspot sessions from MikroTik to radius_accounts.
     * Returns the number of active sessions found.
     * Throws RuntimeException if router is unreachable — caller must handle.
     */
    public function syncHotspot(MikrotikConnection $conn): int
    {
        $this->client->connect();
        $response = $this->client->command('/ip/hotspot/active/print');
        $this->client->disconnect();

        $sessions = $response['data'] ?? [];

        $this->upsertSessions($conn, 'hotspot', $sessions, function (array $row): array {
            return [
                'username'     => $row['user'] ?? ($row['name'] ?? ''),
                'ipv4_address' => $row['address'] ?? null,
                'uptime'       => $row['uptime'] ?? null,
                'caller_id'    => $row['mac-address'] ?? null,
                'server_name'  => $row['server'] ?? null,
                'profile'      => null,
                'bytes_in'     => isset($row['bytes-in']) ? (int) $row['bytes-in'] : null,
                'bytes_out'    => isset($row['bytes-out']) ? (int) $row['bytes-out'] : null,
            ];
        });

        (new VoucherUsageTracker)->markUsedFromRadacct();

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
                    'bytes_in'     => $mapped['bytes_in'] ?? null,
                    'bytes_out'    => $mapped['bytes_out'] ?? null,
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

        // Close zombie radacct sessions: open sessions (acctstoptime IS NULL) for users
        // that are no longer active on this NAS. This prevents FreeRADIUS from blocking
        // new logins due to stale Simultaneous-Use counts.
        $nasIp = $conn->host ?? null;
        if ($nasIp !== null) {
            $zombieQuery = DB::table('radacct')
                ->whereNull('acctstoptime')
                ->where('nasipaddress', $nasIp);

            if (! empty($activeUsernames)) {
                $zombieQuery->whereNotIn('username', $activeUsernames);
            }

            $zombieQuery->update([
                'acctstoptime'       => $now,
                'acctupdatetime'     => $now,
                'acctterminatecause' => 'NAS-Request',
            ]);
        }
    }
}
