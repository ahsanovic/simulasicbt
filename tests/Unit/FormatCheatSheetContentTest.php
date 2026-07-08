<?php

namespace Tests\Unit;

use Tests\TestCase;

class FormatCheatSheetContentTest extends TestCase
{
    public function test_converts_markdown_headings_to_html(): void
    {
        $html = format_cheat_sheet_content("## Konsep Inti\n\nIni **penting**.");

        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringContainsString('Konsep Inti', $html);
        $this->assertStringContainsString('<strong>penting</strong>', $html);
    }

    public function test_strips_unsafe_html_from_markdown(): void
    {
        $html = format_cheat_sheet_content('<script>alert(1)</script>## Aman');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Aman', $html);
    }

    public function test_converts_markdown_ordered_list_to_html(): void
    {
        $html = format_cheat_sheet_content("1. Soal: Pertanyaan?\n   - A. Opsi A\n   - B. Opsi B");

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('Opsi A', $html);
    }

    public function test_returns_empty_for_blank_input(): void
    {
        $this->assertSame('', format_cheat_sheet_content(null));
        $this->assertSame('', format_cheat_sheet_content('   '));
    }
}
