<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('attempt_type')->default('full')->after('user_id');
            $table->foreignId('parent_attempt_id')
                ->nullable()
                ->after('attempt_type')
                ->constrained('exam_attempts')
                ->nullOnDelete();

            $table->index(['user_id', 'attempt_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['parent_attempt_id']);
            $table->dropIndex(['user_id', 'attempt_type', 'status']);
            $table->dropColumn(['attempt_type', 'parent_attempt_id']);
        });
    }
};
