<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreignId('event_session_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_sessions')
                ->nullOnDelete();

            $table->index(['event_session_id', 'status']);
        });

        // Backfill: give every existing event a default "Sesi 1" (carrying the
        // event's old code/schedule/status) and link its attempts to that session,
        // so existing events keep working under the new per-session model.
        foreach (DB::table('events')->get() as $event) {
            $sessionId = DB::table('event_sessions')->insertGetId([
                'event_id' => $event->id,
                'name' => 'Sesi 1',
                'code' => $event->code ?? strtoupper(Str::random(6)),
                'status' => $event->status,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('exam_attempts')
                ->where('event_id', $event->id)
                ->update(['event_session_id' => $sessionId]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['event_session_id']);
            $table->dropIndex(['event_session_id', 'status']);
            $table->dropColumn('event_session_id');
        });
    }
};
