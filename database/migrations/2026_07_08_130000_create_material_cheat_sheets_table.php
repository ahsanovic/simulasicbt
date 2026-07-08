<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_cheat_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_cheat_sheets');
    }
};
