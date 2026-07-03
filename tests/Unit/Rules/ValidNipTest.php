<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidNip;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidNipTest extends TestCase
{
    #[DataProvider('invalidNipProvider')]
    public function test_it_rejects_invalid_nip(string $nip): void
    {
        $rule = new ValidNip;
        $failed = false;

        $rule->validate('nip', $nip, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public static function invalidNipProvider(): array
    {
        return [
            'too short' => ['12345678901234567'],
            'repeated zeros' => ['000000000000000000'],
            'repeated ones' => ['111111111111111111'],
            'contains dash' => ['19850101201001123-'],
            'contains letters' => ['19850101201001123a'],
        ];
    }

    public function test_it_accepts_valid_nip(): void
    {
        $rule = new ValidNip;
        $failed = false;

        $rule->validate('nip', '198501012010011234', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
