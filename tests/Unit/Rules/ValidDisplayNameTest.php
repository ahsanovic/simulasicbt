<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidDisplayName;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidDisplayNameTest extends TestCase
{
    #[DataProvider('invalidNameProvider')]
    public function test_it_rejects_invalid_display_names(string $name): void
    {
        $rule = new ValidDisplayName;
        $failed = false;

        $rule->validate('name', $name, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public static function invalidNameProvider(): array
    {
        return [
            'html script tag' => ['<script>alert(1)</script>Budi'],
            'url http' => ['Budi http://evil.com'],
            'url https' => ['https://phishing.id'],
            'url www' => ['www.scam.com'],
            'email character' => ['budi@admin.com'],
            'only one letter' => ['A'],
            'numbers only' => ['12'],
            'contains digits' => ['Budi123'],
            'special chars only' => ['---'],
            'leading hyphen' => ['-Budi Santoso'],
            'trailing dot' => ['Budi Santoso.'],
            'curly braces' => ['{Budi}'],
        ];
    }

    #[DataProvider('validNameProvider')]
    public function test_it_accepts_valid_display_names(string $name): void
    {
        $rule = new ValidDisplayName;
        $failed = false;

        $rule->validate('name', $name, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public static function validNameProvider(): array
    {
        return [
            'simple' => ['Budi Santoso'],
            'two letters' => ['Li Na'],
            'hyphenated' => ['Jean-Pierre'],
            'apostrophe' => ["O'Brien"],
            'with title' => ['Dr. Ahmad'],
            'unicode accent' => ['José García'],
        ];
    }
}
