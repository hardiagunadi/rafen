<?php

namespace App\Console\Commands;

use App\Models\MikrotikConnection;
use App\Services\ActiveSessionFetcher;
use App\Services\MikrotikApiClient;
use Illuminate\Console\Command;
use RuntimeException;

class SyncActiveSessions extends Command
{
    protected $signature   = 'sessions:sync';
    protected $description = 'Sync active PPPoE & Hotspot sessions from all online MikroTik routers';

    public function handle(): int
    {
        $routers = MikrotikConnection::where('is_active', true)
            ->where('is_online', true)
            ->get();

        if ($routers->isEmpty()) {
            $this->line('No online routers found.');
            return self::SUCCESS;
        }

        $pppTotal     = 0;
        $hotspotTotal = 0;
        $errors       = [];

        foreach ($routers as $router) {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($router));

            try {
                $pppTotal += $fetcher->syncPpp($router);
            } catch (RuntimeException $e) {
                $errors[] = "[{$router->name}] PPPoE: " . $e->getMessage();
            }

            try {
                $hotspotTotal += $fetcher->syncHotspot($router);
            } catch (RuntimeException $e) {
                $errors[] = "[{$router->name}] Hotspot: " . $e->getMessage();
            }
        }

        foreach ($errors as $err) {
            $this->warn($err);
        }

        $this->info("Sync selesai — PPPoE: {$pppTotal}, Hotspot: {$hotspotTotal} sesi aktif.");

        return self::SUCCESS;
    }
}
