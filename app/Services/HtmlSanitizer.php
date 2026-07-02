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

        $html = Purify::clean($html, [
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,h1,h2,h3,ol,ul,li,a[href|title|target],img[src|alt|width|height|class]',
        ]);

        return $this->normalizeStorageUrls($html);
    }

    private function normalizeStorageUrls(string $html): string
    {
        return preg_replace(
            '#https?://[^/"\'\s]+(/storage/[^"\'\s>]+)#i',
            '$1',
            $html
        ) ?? $html;
    }
}
