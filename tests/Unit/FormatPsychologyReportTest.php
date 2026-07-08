<?php

namespace Tests\Unit;

use Tests\TestCase;

class FormatPsychologyReportTest extends TestCase
{
    public function test_converts_markdown_bold_to_html_bold(): void
    {
        $this->assertSame(
            'Halo <b>Ahsan</b>!',
            format_psychology_report('Halo **Ahsan**!'),
        );
    }

    public function test_strips_unsafe_html_tags(): void
    {
        $this->assertSame(
            'Aman',
            format_psychology_report('<script>alert(1)</script>Aman'),
        );
    }

    public function test_returns_empty_for_blank_input(): void
    {
        $this->assertSame('', format_psychology_report(null));
        $this->assertSame('', format_psychology_report('   '));
    }
}
