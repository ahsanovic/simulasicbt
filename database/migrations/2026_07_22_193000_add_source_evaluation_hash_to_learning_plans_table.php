<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->string('source_evaluation_hash', 64)->nullable()->after('sort_order');
            $table->index(['user_id', 'source_evaluation_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source_evaluation_hash', 'status']);
            $table->dropColumn('source_evaluation_hash');
        });
    }
};
