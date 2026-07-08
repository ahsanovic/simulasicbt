<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->longText('front');
            $table->longText('back');
            $table->string('subject_code', 10);
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->unsignedSmallInteger('repetition_count')->default(0);
            $table->unsignedSmallInteger('forget_count')->default(0);
            $table->timestamp('next_review_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_id']);
            $table->index(['user_id', 'next_review_at']);
            $table->index(['user_id', 'forget_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
