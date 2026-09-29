<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_skb_id')->constrained('jabatan_skbs')->cascadeOnDelete();
            $table->longText('content');
            $table->longText('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_questions');
    }
};
