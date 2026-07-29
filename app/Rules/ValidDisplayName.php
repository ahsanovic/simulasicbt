<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDisplayName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Nama tidak valid.');

            return;
        }

        $name = sanitize_display_name($value);

        if ($name === '') {
            $fail('Nama wajib diisi.');

            return;
        }

        if (mb_strlen($name) < 2) {
            $fail('Nama minimal 2 karakter.');

            return;
        }

        if (mb_strlen($name) > 100) {
            $fail('Nama maksimal 100 karakter.');

            return;
        }

        if (! preg_match('/^[\p{L}\p{M}\s.\'-]+$/u', $name)) {
            $fail('Nama hanya boleh berisi huruf, spasi, titik, tanda hubung, dan apostrof.');

            return;
        }

        if (preg_match('/^[\s.\'-]|[\s.\'-]$/u', $name)) {
            $fail('Nama tidak boleh diawali atau diakhiri dengan karakter khusus.');

            return;
        }

        if (preg_match_all('/[\p{L}\p{M}]/u', $name) < 2) {
            $fail('Nama minimal memuat 2 huruf.');

            return;
        }

        if (preg_match('/(?:https?:\/\/|www\.)/i', $name)) {
            $fail('Nama tidak boleh mengandung tautan.');

            return;
        }

        if (str_contains($name, '@')) {
            $fail('Nama tidak boleh mengandung karakter @.');

            return;
        }
    }
}
