<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('exam_mode')->default('skd')->after('exam_id');
            $table->unsignedSmallInteger('skb_question_count')->nullable()->after('exam_mode');
            $table->unsignedSmallInteger('skb_correct_score')->nullable()->after('skb_question_count');
            $table->string('skd_pin')->nullable()->after('skb_correct_score');
            $table->string('skb_pin')->nullable()->after('skd_pin');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->foreignId('exam_id')->nullable()->change();
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['exam_mode', 'skb_question_count', 'skb_correct_score', 'skd_pin', 'skb_pin']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->foreignId('exam_id')->nullable(false)->change();
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }
};
