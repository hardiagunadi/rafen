<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ovpn_clients', function (Blueprint $table) {
            $table->dropForeign(['mikrotik_connection_id']);
        });

        DB::statement('ALTER TABLE ovpn_clients MODIFY mikrotik_connection_id BIGINT UNSIGNED NULL');

        Schema::table('ovpn_clients', function (Blueprint $table) {
            $table->foreign('mikrotik_connection_id')
                ->references('id')
                ->on('mikrotik_connections')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ovpn_clients', function (Blueprint $table) {
            $table->dropForeign(['mikrotik_connection_id']);
        });

        DB::statement('ALTER TABLE ovpn_clients MODIFY mikrotik_connection_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ovpn_clients', function (Blueprint $table) {
            $table->foreign('mikrotik_connection_id')
                ->references('id')
                ->on('mikrotik_connections')
                ->cascadeOnDelete();
        });
    }
};
