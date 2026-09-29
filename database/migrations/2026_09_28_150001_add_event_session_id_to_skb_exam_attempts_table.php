<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skb_exam_attempts', function (Blueprint $table) {
            $table->foreignId('event_session_id')->nullable()->after('event_id')->constrained('event_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skb_exam_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_session_id');
        });
    }
};
