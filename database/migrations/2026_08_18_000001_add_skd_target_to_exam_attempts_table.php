<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('skd_target', 32)
                ->default('cpns')
                ->after('attempt_type');
        });

        DB::table('exams')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->get()
            ->each(function (object $exam): void {
                $settings = json_decode($exam->settings ?? '{}', true);

                if (! is_array($settings)) {
                    $settings = [];
                }

                if (isset($settings['skd_target'])) {
                    return;
                }

                $settings['skd_target'] = 'cpns';

                DB::table('exams')
                    ->where('id', $exam->id)
                    ->update(['settings' => json_encode($settings)]);
            });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('skd_target');
        });
    }
};
