<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('formation_id')
                ->nullable()
                ->constrained('formations')
                ->nullOnDelete();
            $table->timestamp('formation_selected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formation_id');
            $table->dropColumn('formation_selected_at');
        });
    }
};
