<?php

namespace App\Console\Commands;

use App\Models\CpeDevice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\GenieAcsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCpeGenieacs extends Command
{
    protected $signature   = 'cpe:sync-genieacs';
    protected $description = 'Auto-link GenieACS devices to PPP users based on PPPoE username';

    public function handle(): int
    {
        Log::debug('cpe:sync-genieacs started');

        // Group tenants that have GenieACS configured (or use global config)
        $tenantIds = PppUser::query()
            ->whereDoesntHave('cpeDevice')
            ->distinct()
            ->pluck('owner_id');

        if ($tenantIds->isEmpty()) {
            return self::SUCCESS;
        }

        $linked  = 0;
        $skipped = 0;

        // Build per-tenant GenieACS client map
        $clientMap = [];
        foreach ($tenantIds as $ownerId) {
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            $clientMap[$ownerId] = $settings
                ? GenieAcsClient::fromTenantSettings($settings)
                : new GenieAcsClient();
        }

        // Fetch all GenieACS devices once per unique client (group by base URL)
        $devicesByUrl = [];
        foreach ($clientMap as $ownerId => $client) {
            $url = $client->getBaseUrl();
            if (! isset($devicesByUrl[$url])) {
                // Ensure default presets exist in this GenieACS instance so all
                // modems receive the correct CR credentials and inform interval.
                try {
                    $client->ensureDefaultPresets();
                } catch (\Throwable $e) {
                    Log::warning('cpe:sync-genieacs: ensureDefaultPresets failed', [
                        'url'   => $url,
                        'error' => $e->getMessage(),
                    ]);
                }

                $devicesByUrl[$url] = [
                    'client'  => $client,
                    'devices' => $client->listDevices(),
                ];
            }
        }

        // Build lookup: pppoe_username → genieacs device doc, per base URL
        $lookupByUrl = [];
        foreach ($devicesByUrl as $url => $data) {
            $lookup = [];
            foreach ($data['devices'] as $dev) {
                // Try to extract PPPoE username from device doc
                $username = $data['client']->getParamValue($dev, 'pppoe_username');
                if ($username) {
                    $lookup[strtolower($username)] = $dev;
                }
            }
            $lookupByUrl[$url] = $lookup;
        }

        // Process unlinked PPP users
        $unlinked = PppUser::query()->whereDoesntHave('cpeDevice')->get();

        foreach ($unlinked as $pppUser) {
            $client = $clientMap[$pppUser->owner_id] ?? new GenieAcsClient();
            $url    = $client->getBaseUrl();
            $lookup = $lookupByUrl[$url] ?? [];
            $key    = strtolower($pppUser->username);

            if (! isset($lookup[$key])) {
                $skipped++;
                continue;
            }

            $genieDevice = $lookup[$key];
            $profile     = $client->detectParamProfile($genieDevice);

            $cpe = new CpeDevice([
                'ppp_user_id' => $pppUser->id,
                'owner_id'    => $pppUser->owner_id,
            ]);
            $cpe->updateFromGenieacs($genieDevice);
            $cpe->save();

            // Trigger background refresh of full parameter tree
            try {
                $rootObj = isset($genieDevice['InternetGatewayDevice'])
                    ? 'InternetGatewayDevice'
                    : 'Device';
                $client->refreshObject($genieDevice['_id'], $rootObj);
            } catch (\Throwable) {
                // Non-fatal — params will be fetched on next inform
            }

            Log::info("cpe:sync-genieacs linked {$pppUser->username} → {$genieDevice['_id']}");
            $this->line("Linked: {$pppUser->username} → {$genieDevice['_id']}");
            $linked++;
        }

        if ($linked > 0) {
            Log::info("cpe:sync-genieacs done. Linked: {$linked}");
        }
        $this->info("Done. Linked: {$linked}, No device found: {$skipped}");

        return self::SUCCESS;
    }
}
