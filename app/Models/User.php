<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'nip',
        'nik',
        'instansi_id',
        'formation_id',
        'formation_selected_at',
        'ghost_race_rival_user_id',
        'ghost_race_notifications_muted',
        'ghost_race_last_seen_gap',
        'is_pegawai',
        'google_id',
        'password',
        'role',
        'is_active',
        'last_seen_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'is_pegawai' => 'boolean',
            'last_seen_at' => 'datetime',
            'instansi_id' => 'integer',
            'formation_id' => 'integer',
            'formation_selected_at' => 'datetime',
            'ghost_race_rival_user_id' => 'integer',
            'ghost_race_notifications_muted' => 'boolean',
            'ghost_race_last_seen_gap' => 'integer',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Registered in a Mode Ujian event that is currently running. */
    public function isActiveModeUjianParticipant(): bool
    {
        return EventParticipant::query()
            ->where('user_id', $this->id)
            ->whereHas('event', fn ($query) => $query->where('is_mode_ujian', true)->where('status', EventStatus::Active))
            ->exists();
    }

    /** Where this user lands after logging in (and when opening a login page while logged in). */
    public function homeUrl(): string
    {
        if ($this->role === UserRole::Peserta && $this->isActiveModeUjianParticipant()) {
            return route('peserta.mode-ujian.dashboard');
        }

        return match ($this->role) {
            UserRole::Admin => route('admin.dashboard'),
            UserRole::Peserta => route('peserta.dashboard'),
        };
    }

    public function isPeserta(): bool
    {
        return $this->role === UserRole::Peserta;
    }

    public function usesGoogleAuth(): bool
    {
        return ! $this->is_pegawai && $this->google_id !== null;
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function hostedDuels(): HasMany
    {
        return $this->hasMany(DuelSession::class, 'host_user_id');
    }

    public function opponentDuels(): HasMany
    {
        return $this->hasMany(DuelSession::class, 'opponent_user_id');
    }

    public function createdQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'created_by');
    }

    public function audioLearningSessions(): HasMany
    {
        return $this->hasMany(AudioLearningSession::class);
    }

    public function testimonial(): HasOne
    {
        return $this->hasOne(Testimonial::class);
    }

    public function testimonialReactions(): HasMany
    {
        return $this->hasMany(TestimonialReaction::class);
    }

    public function flashcardReviewSessions(): HasMany
    {
        return $this->hasMany(FlashcardReviewSession::class);
    }

    public function dailyActivityLogs(): HasMany
    {
        return $this->hasMany(DailyActivityLog::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    public function xpRewards(): HasMany
    {
        return $this->hasMany(XpReward::class);
    }

    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    public function helpItems(): HasMany
    {
        return $this->hasMany(UserHelpItem::class);
    }

    public function learningPlans(): HasMany
    {
        return $this->hasMany(LearningPlan::class);
    }
}
