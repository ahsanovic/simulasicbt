<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The browser exam room (Mode Ujian) accepts answers only from the device that
 * opened the room last, so a recovered old PC cannot overwrite newer answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('active_device_token', 40)->nullable();
        });

        Schema::table('skb_exam_attempts', function (Blueprint $table) {
            $table->string('active_device_token', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('active_device_token');
        });

        Schema::table('skb_exam_attempts', function (Blueprint $table) {
            $table->dropColumn('active_device_token');
        });
    }
};
