<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skb_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skb_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('selected_option_id')->nullable()->constrained('skb_question_options')->nullOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->boolean('is_marked')->default(false);
            $table->timestamps();

            $table->unique(['skb_exam_attempt_id', 'skb_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_exam_answers');
    }
};
