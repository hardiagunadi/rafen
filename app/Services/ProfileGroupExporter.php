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

            // For sql mode: create/update IP pool on MikroTik first
            if ($group->ip_pool_mode === 'sql' && $group->type === 'pppoe') {
                $this->exportIpPool($client, $group, $poolName);
            }

            $this->exportProfile($client, $group, $poolName);
        } finally {
            $client->disconnect();
        }
    }

    private function exportIpPool(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        if (! $poolName) {
            return;
        }

        $rangeStart = trim((string) $group->range_start);
        $rangeEnd   = trim((string) $group->range_end);

        if ($rangeStart === '' || $rangeEnd === '') {
            return;
        }

        $ranges = $rangeStart.'-'.$rangeEnd;

        $existingId = $this->findId($client, '/ip/pool/print', ['name' => $poolName]);

        if ($existingId) {
            $client->command('/ip/pool/set', ['numbers' => $existingId, 'ranges' => $ranges]);
        } else {
            $client->command('/ip/pool/add', ['name' => $poolName, 'ranges' => $ranges]);
        }
    }

    private function exportProfile(MikrotikApiClient $client, ProfileGroup $group, ?string $poolName): void
    {
        $profileName = trim((string) $group->name);
        if ($profileName === '') {
            throw new RuntimeException('Nama profil group belum diisi.');
        }

        $isPppProfile = $group->type === 'pppoe';
        [$basePath, $attributes] = $isPppProfile
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
        $localAddress = trim((string) $group->ip_address);
        if ($localAddress === '') {
            throw new RuntimeException('IP lokal belum diisi pada profil group "'.$group->name.'".');
        }

        $attributes = [
            'local-address' => $localAddress,
            'comment'       => 'added by TMDRadius',
        ];

        // remote-address: sql mode → use pool (RADIUS sends Framed-IP-Address,
        //   but pool must exist on MikroTik as the PPP profile still needs one)
        // group_only → use named pool on MikroTik
        if ($group->ip_pool_mode === 'sql' || $group->ip_pool_mode === 'group_only') {
            $attributes['remote-address'] = $poolName ?? '';
        }

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
        // Use explicit ip_pool_name if set; otherwise 'none' so MikroTik assigns IP directly
        $explicitPool = trim((string) $group->ip_pool_name);
        $attributes = [
            'address-pool' => $explicitPool !== '' ? $explicitPool : 'none',
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

    /**
     * @param  array<string, string>  $queries
     */
    private function findId(MikrotikApiClient $client, string $path, array $queries): ?string
    {
        $response = $client->command($path, [], $queries);

        return $response['data'][0]['.id'] ?? null;
    }
}
