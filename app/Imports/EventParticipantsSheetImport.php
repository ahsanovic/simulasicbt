<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Imports\Concerns\ValidatesEventParticipantImportRows;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Formation;
use App\Models\JabatanSkb;
use App\Models\User;
use App\Support\ModeUjianPassword;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plain PHP delegate (no Maatwebsite Excel interfaces) holding the row-write
 * logic, shared by the sync (EventParticipantsImport) and queued
 * (EventParticipantsQueuedImport) wrappers — mirrors the SkbQuestionsSheetImport
 * pattern established for the same reason.
 */
class EventParticipantsSheetImport
{
    use ValidatesEventParticipantImportRows;

    /** @var array<string, int> session name (lowercased) => id, cached per import run */
    private array $sessionIdsByName = [];

    public function __construct(
        private readonly int $eventId,
        private readonly bool $requiresSkbJabatan,
    ) {}

    public function collection(Collection $rows): void
    {
        $rows = $this->filterEventParticipantRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->importRow($row);
            }
        });
    }

    private function importRow(Collection|array $row): void
    {
        $name = trim((string) $row['nama']);
        $nik = trim((string) $row['nik']);
        $jabatanLabel = trim((string) $row['jabatan']);
        $sessionId = $this->resolveSessionId(trim((string) $row['sesi']));

        $user = User::query()->updateOrCreate(
            ['nik' => $nik],
            [
                'name' => $name,
                'email' => $nik.'@mode-ujian.local',
                'password' => ModeUjianPassword::hash($nik),
                'role' => UserRole::Peserta,
                'is_pegawai' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $formationId = null;
        $jabatanSkbId = null;

        if ($this->requiresSkbJabatan) {
            $jabatanSkbId = JabatanSkb::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($jabatanLabel)])
                ->value('id');
        } else {
            $formationId = Formation::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($jabatanLabel)])
                ->value('id');
        }

        EventParticipant::query()->updateOrCreate(
            ['event_id' => $this->eventId, 'user_id' => $user->id],
            [
                'event_session_id' => $sessionId,
                'name' => $name,
                'nik' => $nik,
                'jabatan_label' => $jabatanLabel,
                'formation_id' => $formationId,
                'jabatan_skb_id' => $jabatanSkbId,
            ],
        );
    }

    private function resolveSessionId(string $sessionName): ?int
    {
        $key = Str::lower($sessionName);

        if (! array_key_exists($key, $this->sessionIdsByName)) {
            $this->sessionIdsByName[$key] = EventSession::query()
                ->where('event_id', $this->eventId)
                ->whereRaw('LOWER(name) = ?', [$key])
                ->value('id');
        }

        return $this->sessionIdsByName[$key];
    }
}
