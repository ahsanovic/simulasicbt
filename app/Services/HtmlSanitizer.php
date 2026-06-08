<?php

namespace App\Services;

use Stevebauman\Purify\Facades\Purify;

class HtmlSanitizer
{
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return Purify::clean($html);
    }
}
