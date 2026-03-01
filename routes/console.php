<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ping semua router MikroTik aktif setiap 5 menit
Schedule::command('mikrotik:ping-once')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Reset status_bayar ke belum_bayar untuk user yang jatuh temponya sudah tiba (setiap hari jam 06:55)
Schedule::command('billing:reset-status')
    ->dailyAt('06:55')
    ->withoutOverlapping()
    ->runInBackground();

// Generate invoice untuk user PPP yang jatuh tempo dalam 14 hari ke depan (setiap hari jam 07:00)
Schedule::command('invoice:generate-upcoming --days=14')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();
