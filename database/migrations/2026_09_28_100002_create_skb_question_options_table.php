<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skb_question_id')->constrained('skb_questions')->cascadeOnDelete();
            $table->string('label', 1);
            $table->string('content_type')->default('text');
            $table->text('content')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_question_options');
    }
};
