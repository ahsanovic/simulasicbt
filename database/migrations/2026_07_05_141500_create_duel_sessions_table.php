<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duel_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_bot_opponent')->default(false);
            $table->json('question_ids');
            $table->string('status')->default('waiting');
            $table->string('match_type')->default('random');
            $table->unsignedSmallInteger('duration_minutes')->default(10);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('host_attempt_id')->nullable();
            $table->unsignedBigInteger('opponent_attempt_id')->nullable();
            $table->unsignedTinyInteger('host_progress')->default(0);
            $table->unsignedTinyInteger('opponent_progress')->default(0);
            $table->timestamp('host_finished_at')->nullable();
            $table->timestamp('opponent_finished_at')->nullable();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'match_type']);
            $table->index('host_user_id');
            $table->index('opponent_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_sessions');
    }
};
