<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\EventParticipant;

/**
 * One NIK is one participant in one session of one open offline event. A NIK
 * already in this event may only be re-added to its own session (to update
 * its data), and a NIK still in another event that is not closed (draft or
 * active) cannot be added until that event is closed.
 */
class EventParticipantNikGuard
{
    /**
     * @param  array<string, int|null>  $targets  NIK => session it is being added to
     * @return array<string, string> NIK => why it cannot be added
     */
    public function conflicts(int $eventId, array $targets): array
    {
        $conflicts = [];

        // A 16-digit NIK used as an array key turns into an int; compare as
        // strings so users.nik (varchar) stays on its index.
        foreach (array_chunk(array_map('strval', array_keys($targets)), 1000) as $niks) {
            $rows = EventParticipant::query()
                ->join('users', 'users.id', '=', 'event_participants.user_id')
                ->join('events', 'events.id', '=', 'event_participants.event_id')
                ->leftJoin('event_sessions', 'event_sessions.id', '=', 'event_participants.event_session_id')
                ->whereIn('users.nik', $niks)
                ->where(fn ($query) => $query
                    ->where('event_participants.event_id', $eventId)
                    ->orWhere('events.status', '!=', EventStatus::Closed->value))
                ->get([
                    'users.nik',
                    'event_participants.event_id',
                    'event_participants.event_session_id',
                    'events.name as event_name',
                    'event_sessions.name as session_name',
                ]);

            foreach ($rows as $row) {
                $nik = (string) $row->nik;

                if ((int) $row->event_id !== $eventId) {
                    $conflicts[$nik] = "NIK masih terdaftar di event \"{$row->event_name}\" yang belum ditutup. Tutup event tersebut terlebih dahulu.";

                    continue;
                }

                $targetSessionId = $targets[$nik] ?? null;

                if ($row->event_session_id !== null && $targetSessionId !== null && (int) $row->event_session_id !== $targetSessionId) {
                    $conflicts[$nik] ??= "NIK sudah terdaftar di sesi \"{$row->session_name}\" pada event ini. Satu peserta hanya boleh di satu sesi.";
                }
            }
        }

        return $conflicts;
    }
}
