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

    public function resolveForDisplay(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return preg_replace_callback(
            '#\bsrc=(["\'])(?!https?://)((?:/)?storage/[^"\']+)\1#i',
            function (array $matches): string {
                $path = ltrim($matches[2], '/');

                return 'src='.$matches[1].storage_asset($path).$matches[1];
            },
            $html
        ) ?? $html;
    }

    private function normalizeStorageUrls(string $html): string
    {
        $html = preg_replace(
            '#https?://[^/"\'\s]+(?:/[^/"\']*)*/storage/([^"\'\s>]+)#i',
            'storage/$1',
            $html
        ) ?? $html;

        return preg_replace(
            '#(?<=["\'])/storage/([^"\'\s>]+)#i',
            'storage/$1',
            $html
        ) ?? $html;
    }
}
