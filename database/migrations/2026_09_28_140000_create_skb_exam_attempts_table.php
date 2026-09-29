<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jabatan_skb_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status')->default('in_progress');
            $table->unsignedSmallInteger('correct_score')->default(0);
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_exam_attempts');
    }
};
