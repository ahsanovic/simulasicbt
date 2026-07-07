<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonial_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimonial_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->timestamps();

            $table->unique(['testimonial_id', 'user_id']);
            $table->index(['testimonial_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonial_reactions');
    }
};
