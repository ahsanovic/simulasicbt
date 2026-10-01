<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * "Mode Sedang Ujian": while an official exam runs on this production app the
 * public simulasi is closed — the normal login and every simulasi page show a
 * notice, and only admins and Mode Ujian participants get in (through
 * route('ujian.login')). Toggled by an admin from Pengaturan; reopening is
 * always manual, the "reopens at" time is only shown to visitors.
 */
final class ExamLockdown
{
    public const BRAND = 'Ujian CBT BKD Provinsi Jawa Timur';

    private const CACHE_KEY = 'exam_lockdown_state';

    private const KEY_ACTIVE = 'exam_lockdown_active';

    private const KEY_REOPENS_AT = 'exam_lockdown_reopens_at';

    private const KEY_MESSAGE = 'exam_lockdown_message';

    public static function active(): bool
    {
        return self::state()['active'];
    }

    public static function reopensAt(): ?CarbonImmutable
    {
        $value = self::state()['reopens_at'];

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }

    public static function message(): ?string
    {
        $value = self::state()['message'];

        return filled($value) ? $value : null;
    }

    public static function save(bool $active, ?string $reopensAt, ?string $message): void
    {
        Setting::setValue(self::KEY_ACTIVE, $active ? '1' : '0', 'exam_lockdown', 'boolean');
        Setting::setValue(self::KEY_REOPENS_AT, (string) $reopensAt, 'exam_lockdown');
        Setting::setValue(self::KEY_MESSAGE, (string) $message, 'exam_lockdown');

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Read on every peserta request, so keep it to one cached lookup.
     *
     * @return array{active: bool, reopens_at: ?string, message: ?string}
     */
    private static function state(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(30), fn () => [
            'active' => Setting::getValue(self::KEY_ACTIVE) === '1',
            'reopens_at' => Setting::getValue(self::KEY_REOPENS_AT),
            'message' => Setting::getValue(self::KEY_MESSAGE),
        ]);
    }
}
