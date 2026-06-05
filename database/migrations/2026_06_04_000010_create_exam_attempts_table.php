<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status')->default('in_progress');
            $table->decimal('score_twk', 8, 2)->nullable();
            $table->decimal('score_tiu', 8, 2)->nullable();
            $table->decimal('score_tkp', 8, 2)->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
