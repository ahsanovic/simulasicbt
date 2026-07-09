<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_help_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_help_items');
    }
};
