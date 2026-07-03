<?php

namespace Tests\Unit;

use Tests\TestCase;

class FormatAiRecommendationTest extends TestCase
{
    public function test_strips_markdown_bold(): void
    {
        $this->assertSame(
            'Saran Tindakan:',
            format_ai_recommendation('**Saran Tindakan:**'),
        );
    }

    public function test_strips_markdown_bold_within_paragraph(): void
    {
        $input = "Halo Budi!\n\n**Saran Tindakan:**\n- Latihan TWK";
        $expected = "Halo Budi!\n\nSaran Tindakan:\n- Latihan TWK";

        $this->assertSame($expected, format_ai_recommendation($input));
    }

    public function test_leaves_plain_text_unchanged(): void
    {
        $text = "Saran Tindakan:\n- Kerjakan simulasi TWK";

        $this->assertSame($text, format_ai_recommendation($text));
    }

    public function test_handles_null_and_empty(): void
    {
        $this->assertSame('', format_ai_recommendation(null));
        $this->assertSame('', format_ai_recommendation('   '));
    }
}
