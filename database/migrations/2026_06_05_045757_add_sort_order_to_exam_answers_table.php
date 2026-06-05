<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('question_id');
            $table->index(['exam_attempt_id', 'sort_order']);
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE exam_answers
                SET sort_order = (
                    SELECT eq.sort_order
                    FROM exam_attempts att
                    INNER JOIN exam_questions eq
                        ON eq.exam_id = att.exam_id
                        AND eq.question_id = exam_answers.question_id
                    WHERE att.id = exam_answers.exam_attempt_id
                )
            ');
        } else {
            DB::statement('
                UPDATE exam_answers ea
                INNER JOIN exam_attempts att ON att.id = ea.exam_attempt_id
                INNER JOIN exam_questions eq ON eq.exam_id = att.exam_id AND eq.question_id = ea.question_id
                SET ea.sort_order = eq.sort_order
            ');
        }
    }

    public function down(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->dropIndex(['exam_attempt_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
