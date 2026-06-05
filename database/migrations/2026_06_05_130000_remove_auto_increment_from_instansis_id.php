<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['instansi_id']);
        });

        DB::statement('ALTER TABLE instansis MODIFY id INT UNSIGNED NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('instansi_id')
                ->references('id')
                ->on('instansis')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['instansi_id']);
        });

        DB::statement('ALTER TABLE instansis MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('instansi_id')
                ->references('id')
                ->on('instansis')
                ->nullOnDelete();
        });
    }
};
