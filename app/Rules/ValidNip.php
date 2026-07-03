<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNip implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            $fail('nip hanya boleh berisi angka tanpa spasi atau tanda hubung.');

            return;
        }

        if (strlen($value) < 18) {
            $fail('nip harus minimal 18 digit.');

            return;
        }

        if (preg_match('/^(\d)\1+$/', $value) === 1) {
            $fail('nip tidak valid.');
        }
    }
}
