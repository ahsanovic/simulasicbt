<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');

            $table->string('nip')->nullable()->after('username');
            $table->unsignedInteger('instansi_id')->nullable()->after('nip');
            $table->boolean('is_pegawai')->default(false)->after('instansi_id');
            $table->string('google_id')->nullable()->unique()->after('is_pegawai');

            $table->string('password')->nullable()->change();

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
            $table->dropColumn(['nip', 'instansi_id', 'is_pegawai', 'google_id']);
            $table->string('phone')->nullable()->after('username');
            $table->string('password')->nullable(false)->change();
        });
    }
};
