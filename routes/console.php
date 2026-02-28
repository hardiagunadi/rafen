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
    ->withoutOverlapping()
    ->runInBackground();
