<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallCron extends Command
{
    protected $signature   = 'schedule:install-cron {--user= : User crontab yang dipakai (default: current user)}';
    protected $description = 'Tambahkan entri cron Laravel scheduler ke crontab sistem';

    public function handle(): int
    {
        $artisan = base_path('artisan');
        $entry   = "* * * * * php {$artisan} schedule:run >> /dev/null 2>&1";

        // Baca crontab saat ini
        exec('crontab -l 2>/dev/null', $lines, $code);
        $current = implode("\n", $lines);

        if (str_contains($current, 'artisan schedule:run')) {
            $this->info('Entri cron scheduler sudah ada.');

            return self::SUCCESS;
        }

        // Tambahkan entri baru
        $newCrontab = trim($current)."\n".$entry."\n";

        $tmp = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tmp, $newCrontab);

        exec('crontab '.escapeshellarg($tmp), $out, $exitCode);
        unlink($tmp);

        if ($exitCode !== 0) {
            $this->error('Gagal menulis crontab. Pastikan user webserver punya izin crontab.');

            return self::FAILURE;
        }

        $this->info('Berhasil menambahkan entri cron: '.$entry);

        return self::SUCCESS;
    }
}
