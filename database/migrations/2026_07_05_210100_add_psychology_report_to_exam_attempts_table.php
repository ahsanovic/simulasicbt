<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->json('answer_behavior')->nullable()->after('question_duration');
            $table->text('psychology_report')->nullable()->after('answer_behavior');
            $table->string('psychology_report_status')->default('skipped')->after('psychology_report');
            $table->timestamp('psychology_report_generated_at')->nullable()->after('psychology_report_status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'answer_behavior',
                'psychology_report',
                'psychology_report_status',
                'psychology_report_generated_at',
            ]);
        });
    }
};
