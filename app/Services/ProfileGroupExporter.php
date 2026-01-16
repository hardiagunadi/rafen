<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use RuntimeException;

class ProfileGroupExporter
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function export(ProfileGroup $group, MikrotikConnection $connection): void
    {
        $client = new MikrotikApiClient($connection);
        $client->connect();

        try {
            $poolName = $this->resolvePoolName($group);

            if ($group->ip_pool_mode === 'group_only') {
                $this->exportPool($client, $group, $poolName);
            }

            $this->exportProfile($client, $group, $poolName);
        } finally {
            $client->disconnect();
        }
    }

    private function exportPool(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        if (! $poolName) {
            throw new RuntimeException('Nama pool Mikrotik belum diisi.');
        }

        $rangeStart = trim((string) $group->range_start);
        $rangeEnd = trim((string) $group->range_end);

        if ($rangeStart === '' || $rangeEnd === '') {
            throw new RuntimeException('Range IP pool belum lengkap.');
        }

        $ranges = $rangeStart.'-'.$rangeEnd;
        $existingId = $this->findId($client, '/ip/pool/print', ['name' => $poolName]);

        $payload = [
            'name' => $poolName,
            'ranges' => $ranges,
        ];

        if ($existingId) {
            $payload['numbers'] = $existingId;
            $client->command('/ip/pool/set', $payload);

            return;
        }

        $client->command('/ip/pool/add', $payload);
    }

    private function exportProfile(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        $profileName = trim((string) $group->name);
        if ($profileName === '') {
            throw new RuntimeException('Nama profil group belum diisi.');
        }

        [$basePath, $attributes] = $group->type === 'pppoe'
            ? ['/ppp/profile', $this->pppProfileAttributes($group, $poolName)]
            : ['/ip/hotspot/user/profile', $this->hotspotProfileAttributes($group, $poolName)];

        $existingId = $this->findId($client, $basePath.'/print', ['name' => $profileName]);

        $payload = array_merge(['name' => $profileName], $attributes);

        if ($existingId) {
            $payload['numbers'] = $existingId;
            $client->command($basePath.'/set', $payload);

            return;
        }

        $client->command($basePath.'/add', $payload);
    }

    private function pppProfileAttributes(ProfileGroup $group, ?string $poolName): array
    {
        $attributes = [
            'remote-address' => $this->resolvePoolAssignment($group, $poolName),
        ];

        $dns = trim((string) $group->dns_servers);
        if ($dns !== '') {
            $attributes['dns-server'] = $dns;
        }

        $parentQueue = trim((string) $group->parent_queue);
        if ($parentQueue !== '') {
            $attributes['parent-queue'] = $parentQueue;
        }

        return $attributes;
    }

    private function hotspotProfileAttributes(ProfileGroup $group, ?string $poolName): array
    {
        $attributes = [
            'address-pool' => $this->resolvePoolAssignment($group, $poolName),
        ];

        $dns = trim((string) $group->dns_servers);
        if ($dns !== '') {
            $attributes['dns-server'] = $dns;
        }

        $parentQueue = trim((string) $group->parent_queue);
        if ($parentQueue !== '') {
            $attributes['parent-queue'] = $parentQueue;
        }

        return $attributes;
    }

    private function resolvePoolName(ProfileGroup $group): ?string
    {
        $poolName = trim((string) $group->ip_pool_name);

        if ($poolName === '') {
            $poolName = trim((string) $group->name);
        }

        return $poolName !== '' ? $poolName : null;
    }

    private function resolvePoolAssignment(ProfileGroup $group, ?string $poolName): string
    {
        if ($group->ip_pool_mode === 'group_only' && $poolName) {
            return $poolName;
        }

        return 'none';
    }

    /**
     * @param  array<string, string>  $queries
     */
    private function findId(MikrotikApiClient $client, string $path, array $queries): ?string
    {
        $response = $client->command($path, [], $queries);

        return $response['data'][0]['.id'] ?? null;
    }
}
