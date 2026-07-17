<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores an INTEGER PRIMARY KEY as a rowid alias that already
        // accepts explicit id values, so only MySQL/MariaDB needs the column
        // altered to drop AUTO_INCREMENT.
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

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
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

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
