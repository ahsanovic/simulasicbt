<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreignId('duel_session_id')
                ->nullable()
                ->after('exam_id')
                ->constrained('duel_sessions')
                ->nullOnDelete();
        });

        Schema::table('duel_sessions', function (Blueprint $table) {
            $table->foreign('host_attempt_id')
                ->references('id')
                ->on('exam_attempts')
                ->nullOnDelete();

            $table->foreign('opponent_attempt_id')
                ->references('id')
                ->on('exam_attempts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duel_sessions', function (Blueprint $table) {
            $table->dropForeign(['host_attempt_id']);
            $table->dropForeign(['opponent_attempt_id']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duel_session_id');
        });
    }
};
