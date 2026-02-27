<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ppp_profiles MODIFY satuan ENUM('bulan', 'hari', 'minggu', 'jam', 'menit') NOT NULL DEFAULT 'bulan'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ppp_profiles MODIFY satuan ENUM('bulan') NOT NULL DEFAULT 'bulan'");
    }
};
