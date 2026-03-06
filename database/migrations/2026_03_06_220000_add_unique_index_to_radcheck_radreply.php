<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate rows before adding unique index
        DB::statement('
            DELETE r1 FROM radcheck r1
            INNER JOIN radcheck r2
            WHERE r1.id > r2.id
              AND r1.username = r2.username
              AND r1.attribute = r2.attribute
        ');

        DB::statement('
            DELETE r1 FROM radreply r1
            INNER JOIN radreply r2
            WHERE r1.id > r2.id
              AND r1.username = r2.username
              AND r1.attribute = r2.attribute
        ');

        Schema::table('radcheck', function (Blueprint $table) {
            $table->unique(['username', 'attribute'], 'radcheck_username_attribute_unique');
        });

        Schema::table('radreply', function (Blueprint $table) {
            $table->unique(['username', 'attribute'], 'radreply_username_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::table('radcheck', function (Blueprint $table) {
            $table->dropUnique('radcheck_username_attribute_unique');
        });

        Schema::table('radreply', function (Blueprint $table) {
            $table->dropUnique('radreply_username_attribute_unique');
        });
    }
};
