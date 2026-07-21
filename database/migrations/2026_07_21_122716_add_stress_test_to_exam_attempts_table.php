<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->boolean('stress_test_enabled')->default(false)->after('help_items_state');
            $table->json('stress_test_telemetry')->nullable()->after('stress_test_enabled');
            $table->json('stress_test_analysis')->nullable()->after('stress_test_telemetry');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'stress_test_enabled',
                'stress_test_telemetry',
                'stress_test_analysis',
            ]);
        });
    }
};
