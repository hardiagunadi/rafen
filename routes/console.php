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

// Sync active PPPoE & Hotspot sessions dari semua router setiap 5 menit
Schedule::command('sessions:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync radcheck/radreply dari ppp_users, hotspot_users, dan vouchers setiap 5 menit
Schedule::command('radius:sync-replies')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Cek atribut RADIUS di DB sudah terdaftar di dictionary, auto-fix jika ada yang baru (setiap hari jam 06:00)
Schedule::command('radius:check-dictionary --fix')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

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
