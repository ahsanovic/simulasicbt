<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ghost_race_rival_user_id')
                ->nullable()
                ->after('formation_selected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('ghost_race_notifications_muted')
                ->default(false)
                ->after('ghost_race_rival_user_id');
            $table->unsignedTinyInteger('ghost_race_last_seen_gap')
                ->nullable()
                ->after('ghost_race_notifications_muted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ghost_race_rival_user_id');
            $table->dropColumn([
                'ghost_race_notifications_muted',
                'ghost_race_last_seen_gap',
            ]);
        });
    }
};
