<?php

namespace App\Services\ModeUjianRoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Only the device (or tab) that opened the room last may save answers:
 * moving a participant to another PC must not let the old PC, once it
 * recovers, overwrite newer answers with ones it was still holding.
 */
final class DeviceToken
{
    public static function issue(Model $attempt): string
    {
        $token = Str::random(40);

        $attempt->newQuery()->whereKey($attempt->getKey())->update(['active_device_token' => $token]);
        $attempt->setAttribute('active_device_token', $token);
        $attempt->syncOriginalAttribute('active_device_token');

        return $token;
    }

    public static function matches(Model $attempt, ?string $token): bool
    {
        $active = $attempt->getAttribute('active_device_token');

        return is_string($active) && is_string($token) && hash_equals($active, $token);
    }
}
