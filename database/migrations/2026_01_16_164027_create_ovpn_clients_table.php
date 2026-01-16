<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ovpn_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_connection_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('common_name')->unique();
            $table->string('vpn_ip')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ovpn_clients');
    }
};
