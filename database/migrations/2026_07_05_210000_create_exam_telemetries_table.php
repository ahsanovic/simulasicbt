<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->integer('question_number');
            $table->integer('time_spent_seconds');
            $table->boolean('is_changed_at_last_minute')->default(false);
            $table->boolean('changed_from_correct_to_wrong')->default(false);
            $table->integer('remaining_time_seconds');
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_telemetries');
    }
};
