<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('active');
            $table->string('color', 20)->default('indigo');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('learning_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('learning_plan_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('category', 30)->default('lainnya');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('todo');
            $table->date('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['learning_plan_id', 'status', 'sort_order']);
            $table->index(['learning_plan_id', 'parent_id']);
            $table->index(['scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_plan_tasks');
        Schema::dropIfExists('learning_plans');
    }
};
