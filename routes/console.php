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

// Reset status_bayar ke belum_bayar untuk user yang jatuh temponya sudah tiba (setiap menit, idempoten)
Schedule::command('billing:reset-status')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Isolir user PPP yang overdue dan belum bayar (setiap menit, gap ~1 menit dari jatuh tempo)
Schedule::command('billing:isolate-overdue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Generate invoice untuk user PPP yang jatuh tempo dalam 14 hari ke depan (setiap hari jam 07:00)
Schedule::command('invoice:generate-upcoming --days=15')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Deteksi voucher yang sudah digunakan berdasarkan radacct (setiap menit)
Schedule::command('vouchers:mark-used')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Hapus voucher expired dari DB dan RADIUS (setiap hari jam 07:05)
Schedule::command('vouchers:expire')
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->runInBackground();

// Kirim WA reminder perpanjangan subscription (7 hari & 1 hari sebelum expired) — jam 09:00
Schedule::command('subscription:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Pastikan service WA Gateway lokal selalu aktif di background
Schedule::command('wa-gateway:ensure-running')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
