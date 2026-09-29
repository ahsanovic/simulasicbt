<?php

namespace App\Livewire\Admin\Events;

use App\Enums\UserRole;
use App\Livewire\Concerns\HandlesImportErrorModal;
use App\Models\CoinTransaction;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\JabatanSkb;
use App\Models\User;
use App\Models\XpReward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Peserta Mode Ujian')]
class Participants extends Component
{
    use HandlesImportErrorModal, WithPagination;

    public Event $event;

    public string $search = '';

    #[Url(except: null)]
    public ?int $sessionFilter = null;

    public bool $showImportModal = false;

    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editNik = '';

    public ?int $editSessionId = null;

    public ?int $editFormationId = null;

    public ?int $editJabatanSkbId = null;

    public string $formationSearch = '';

    public string $jabatanSkbSearch = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->mountImportErrorModal();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSessionFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $participantId): void
    {
        $participant = $this->event->participants()->findOrFail($participantId);

        $this->editingId = $participant->id;
        $this->editName = $participant->name;
        $this->editNik = $participant->nik;
        $this->editSessionId = $participant->event_session_id;
        $this->editFormationId = $participant->formation_id;
        $this->editJabatanSkbId = $participant->jabatan_skb_id;
        $this->formationSearch = $participant->formation?->name ?? '';
        $this->jabatanSkbSearch = $participant->jabatanSkb?->name ?? '';
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function updatedFormationSearch(): void
    {
        // Typing invalidates whatever was previously clicked — the admin must
        // pick again from the dropdown. Prevents saving free-typed text that
        // was never actually matched to a real Formation.
        $this->editFormationId = null;
    }

    public function updatedJabatanSkbSearch(): void
    {
        // Same reasoning as updatedFormationSearch(): typing without picking
        // a suggestion must not silently keep a stale/mismatched selection.
        $this->editJabatanSkbId = null;
    }

    public function selectFormation(int $formationId): void
    {
        $formation = Formation::query()->findOrFail($formationId);
        $this->editFormationId = $formation->id;
        $this->formationSearch = $formation->name;
    }

    public function selectJabatanSkb(int $jabatanSkbId): void
    {
        $jabatan = JabatanSkb::query()->findOrFail($jabatanSkbId);
        $this->editJabatanSkbId = $jabatan->id;
        $this->jabatanSkbSearch = $jabatan->name;
    }

    public function saveForm(): void
    {
        $requiresSkbJabatan = $this->event->exam_mode->includesSkb();

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editNik' => ['required', 'digits:16'],
            'editSessionId' => ['nullable', 'integer', 'exists:event_sessions,id'],
            'editJabatanSkbId' => [$requiresSkbJabatan ? 'required' : 'nullable', 'integer', 'exists:jabatan_skbs,id'],
            'editFormationId' => [! $requiresSkbJabatan ? 'required' : 'nullable', 'integer', 'exists:formations,id'],
        ], [], [
            'editName' => 'Nama',
            'editNik' => 'NIK',
            'editSessionId' => 'Sesi',
            'editJabatanSkbId' => 'Jabatan SKB',
            'editFormationId' => 'Jabatan (Formasi)',
        ]);

        // jabatan_label always comes from the selected record itself, never
        // from the raw search box text — guarantees the stored label can
        // only ever be a jabatan that genuinely exists in the bank.
        $jabatanLabel = $requiresSkbJabatan
            ? JabatanSkb::query()->findOrFail($validated['editJabatanSkbId'])->name
            : Formation::query()->findOrFail($validated['editFormationId'])->name;

        if ($this->editingId) {
            $participant = $this->event->participants()->findOrFail($this->editingId);

            $participant->update([
                'name' => $validated['editName'],
                'nik' => $validated['editNik'],
                'event_session_id' => $validated['editSessionId'] ?: null,
                'jabatan_label' => $jabatanLabel,
                'formation_id' => $requiresSkbJabatan ? null : $validated['editFormationId'],
                'jabatan_skb_id' => $requiresSkbJabatan ? $validated['editJabatanSkbId'] : null,
            ]);

            $participant->user()->update([
                'name' => $validated['editName'],
                'nik' => $validated['editNik'],
            ]);

            session()->flash('success', 'Data peserta diperbarui.');
            $this->closeFormModal();

            return;
        }

        // Manual add: mirrors the Excel-import row-write logic exactly
        // (find-or-create the User by NIK, then upsert the EventParticipant),
        // so a NIK already used in another event correctly reuses that same
        // login instead of creating a duplicate account.
        $user = User::query()->updateOrCreate(
            ['nik' => $validated['editNik']],
            [
                'name' => $validated['editName'],
                'email' => $validated['editNik'].'@mode-ujian.local',
                'password' => Hash::make($validated['editNik']),
                'role' => UserRole::Peserta,
                'is_pegawai' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $participant = EventParticipant::query()->updateOrCreate(
            ['event_id' => $this->event->id, 'user_id' => $user->id],
            [
                'event_session_id' => $validated['editSessionId'] ?: null,
                'name' => $validated['editName'],
                'nik' => $validated['editNik'],
                'jabatan_label' => $jabatanLabel,
                'formation_id' => $requiresSkbJabatan ? null : $validated['editFormationId'],
                'jabatan_skb_id' => $requiresSkbJabatan ? $validated['editJabatanSkbId'] : null,
            ],
        );

        session()->flash('success', $participant->wasRecentlyCreated
            ? 'Peserta berhasil ditambahkan.'
            : 'Peserta sudah terdaftar di event ini — data diperbarui.');
        $this->closeFormModal();
    }

    public function delete(int $participantId): void
    {
        $participant = $this->event->participants()->findOrFail($participantId);

        DB::transaction(function () use ($participant) {
            // SKB data (skb_exam_attempts -> skb_exam_answers) cascades away
            // via its event_participant_id foreign key, but the SKD
            // ExamAttempt is only linked by event_id/user_id — no FK to
            // event_participants — so it (and everything scored from it)
            // must be torn down here explicitly, or it lingers as a ghost
            // row on the SKD livescore board after the participant is gone.
            $attempts = ExamAttempt::query()
                ->where('event_id', $participant->event_id)
                ->where('user_id', $participant->user_id)
                ->get();

            foreach ($attempts as $attempt) {
                $attempt->answers()->delete();
                $attempt->telemetries()->delete();

                XpReward::query()
                    ->where('source_type', ExamAttempt::class)
                    ->where('source_id', $attempt->id)
                    ->delete();

                CoinTransaction::query()
                    ->where('source_type', ExamAttempt::class)
                    ->where('source_id', $attempt->id)
                    ->delete();

                $attempt->delete();
            }

            $participant->delete();
        });

        session()->flash('success', 'Peserta dihapus dari event ini.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'editName', 'editNik', 'editSessionId', 'editFormationId', 'editJabatanSkbId', 'formationSearch', 'jabatanSkbSearch']);
        $this->resetValidation();
    }

    public function render()
    {
        $participants = $this->event->participants()
            ->with(['user', 'formation', 'jabatanSkb', 'eventSession'])
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('nik', 'like', "%{$this->search}%");
            }))
            ->when($this->sessionFilter, fn ($q) => $q->where('event_session_id', $this->sessionFilter))
            ->latest()
            ->paginate(20);

        $sessions = $this->event->sessions()->orderBy('name')->get(['id', 'name']);

        $formationSuggestions = strlen($this->formationSearch) >= 1
            ? Formation::query()->where('name', 'like', "%{$this->formationSearch}%")->orderBy('name')->limit(10)->get()
            : collect();

        $jabatanSkbSuggestions = strlen($this->jabatanSkbSearch) >= 1
            ? JabatanSkb::query()->where('is_active', true)->where('name', 'like', "%{$this->jabatanSkbSearch}%")->orderBy('name')->limit(10)->get()
            : collect();

        return view('livewire.admin.events.participants', compact(
            'participants', 'sessions', 'formationSuggestions', 'jabatanSkbSuggestions',
        ));
    }
}
