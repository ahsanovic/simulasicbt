<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_instansi');
            $table->text('story');
            $table->text('turning_point')->nullable();
            $table->json('feature_tags');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedInteger('hearts_count')->default(0);
            $table->unsignedInteger('fires_count')->default(0);
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['hearts_count', 'fires_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
