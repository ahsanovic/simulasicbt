<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answer versioning: the exam room sends an always-increasing number with
 * every request (X-Exam-Seq) and an answer is only written when that number
 * is newer than the stored one. A save that was cancelled in the browser
 * (timeout on a bad connection) but still reaches the server late can then
 * no longer overwrite the newer pick the participant saved after it.
 *
 * Appended at the end of the table (no ->after()) so MySQL 8 can add it as an
 * instant metadata change instead of rebuilding the large answer tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->unsignedInteger('answer_version')->default(0);
        });

        Schema::table('skb_exam_answers', function (Blueprint $table) {
            $table->unsignedInteger('answer_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->dropColumn('answer_version');
        });

        Schema::table('skb_exam_answers', function (Blueprint $table) {
            $table->dropColumn('answer_version');
        });
    }
};
