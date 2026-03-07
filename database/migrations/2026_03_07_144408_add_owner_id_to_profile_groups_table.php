<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('name')->index();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        // Backfill: assign owner_id from mikrotik_connections where FK exists
        DB::statement('
            UPDATE profile_groups pg
            JOIN mikrotik_connections mc ON mc.id = pg.mikrotik_connection_id
            SET pg.owner_id = mc.owner_id
            WHERE pg.mikrotik_connection_id IS NOT NULL
        ');

        // Backfill: for rows with NULL mikrotik_connection_id but owner name matches a user,
        // assign the first admin user (single-tenant bootstrap)
        $fallbackOwnerId = DB::table('users')
            ->where('role', 'administrator')
            ->whereNull('parent_id')
            ->value('id');

        if ($fallbackOwnerId) {
            DB::table('profile_groups')
                ->whereNull('owner_id')
                ->update(['owner_id' => $fallbackOwnerId]);
        }
    }

    public function down(): void
    {
        Schema::table('profile_groups', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
