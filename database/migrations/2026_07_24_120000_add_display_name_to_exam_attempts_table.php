<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            // Peserta-confirmed name for this attempt only (does not change users.name).
            // Falls back to the user's account name when null. Used on the live
            // display and printed on the certificate so it matches what the
            // participant actually confirmed before the exam started.
            $table->string('display_name')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
