<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('skd_pin')->nullable()->after('code');
            $table->string('skb_pin')->nullable()->after('skd_pin');
        });

        // Carry forward any PIN an admin already set at the event level onto
        // every session under that event, so nothing already configured
        // today gets silently wiped by the column move.
        DB::table('events')
            ->where('is_mode_ujian', true)
            ->whereNotNull('skd_pin')
            ->orWhereNotNull('skb_pin')
            ->select('id', 'skd_pin', 'skb_pin')
            ->orderBy('id')
            ->get()
            ->each(function ($event) {
                DB::table('event_sessions')
                    ->where('event_id', $event->id)
                    ->update(['skd_pin' => $event->skd_pin, 'skb_pin' => $event->skb_pin]);
            });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['skd_pin', 'skb_pin']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('skd_pin')->nullable();
            $table->string('skb_pin')->nullable();
        });

        DB::table('event_sessions')
            ->whereNotNull('skd_pin')
            ->orWhereNotNull('skb_pin')
            ->select('event_id', 'skd_pin', 'skb_pin')
            ->orderBy('id')
            ->get()
            ->each(function ($session) {
                DB::table('events')
                    ->where('id', $session->event_id)
                    ->update(['skd_pin' => $session->skd_pin, 'skb_pin' => $session->skb_pin]);
            });

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn(['skd_pin', 'skb_pin']);
        });
    }
};
