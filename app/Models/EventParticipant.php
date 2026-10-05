<?php

namespace App\Models;

use App\Support\LiveScoreCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    /** Roster/session changes show on the livescore boards straight away (see LiveScoreCache). */
    protected static function booted(): void
    {
        static::saved(fn (EventParticipant $participant) => LiveScoreCache::bust($participant->event_id));
        static::deleted(fn (EventParticipant $participant) => LiveScoreCache::bust($participant->event_id));
    }

    protected $fillable = [
        'event_id',
        'event_session_id',
        'user_id',
        'name',
        'nik',
        'jabatan_label',
        'formation_id',
        'jabatan_skb_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }
}
