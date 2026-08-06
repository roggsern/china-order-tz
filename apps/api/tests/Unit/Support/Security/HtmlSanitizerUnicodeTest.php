<?php

namespace Tests\Unit\Support\Security;

use App\Support\Security\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerUnicodeTest extends TestCase
{
    public static function unicodeSamples(): array
    {
        return [
            'bullet' => ['•'],
            'en_dash' => ['–'],
            'ellipsis' => ['…'],
            'ldquo' => ['“'],
            'rdquo' => ['”'],
            'apos' => ['’'],
            'degree' => ['°'],
            'tm' => ['™'],
            'emoji' => ['✅'],
            'mixed' => ["Hello • world – “quoted” ’ °™ ✅"],
        ];
    }

    #[DataProvider('unicodeSamples')]
    public function test_unicode_plain_text_survives_unchanged(string $sample): void
    {
        $this->assertSame($sample, HtmlSanitizer::sanitize($sample));
    }

    public function test_allowed_html_with_unicode_survives(): void
    {
        $html = '<p>Features • Waterproof – “IP67” ✅</p><ul><li>Size: 10°</li></ul>';
        $out = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('•', $out);
        $this->assertStringContainsString('–', $out);
        $this->assertStringContainsString('“', $out);
        $this->assertStringContainsString('”', $out);
        $this->assertStringContainsString('✅', $out);
        $this->assertStringContainsString('10°', $out);
        $this->assertStringContainsString('<p>', $out);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<li>', $out);
        $this->assertStringNotContainsString('<html', strtolower($out));
        $this->assertStringNotContainsString('<body', strtolower($out));
        $this->assertStringNotContainsString('<head', strtolower($out));
    }

    public function test_repeated_sanitization_is_idempotent_for_unicode(): void
    {
        $sample = "Hello • world – “quotes” ’ °™ ✅";
        $once = HtmlSanitizer::sanitize($sample);
        $twice = HtmlSanitizer::sanitize($once);
        $thrice = HtmlSanitizer::sanitize($twice);

        $this->assertSame($once, $twice);
        $this->assertSame($twice, $thrice);
        $this->assertSame($sample, $once);
    }

    public function test_script_tags_are_stripped(): void
    {
        $out = HtmlSanitizer::sanitize('<p>Safe</p><script>alert(1)</script>');
        $this->assertStringContainsString('Safe', $out);
        $this->assertStringNotContainsString('<script', strtolower($out));
        $this->assertStringNotContainsString('alert', $out);
    }

    public function test_javascript_urls_are_stripped(): void
    {
        $out = HtmlSanitizer::sanitize('<a href="javascript:alert(1)">Click</a>');
        $this->assertStringNotContainsString('javascript:', strtolower($out));
        $this->assertStringNotContainsString('href=', strtolower($out));
    }

    public function test_event_handler_attributes_are_stripped(): void
    {
        $out = HtmlSanitizer::sanitize('<p onclick="alert(1)" onmouseover="evil()">Text •</p>');
        $this->assertStringContainsString('Text •', $out);
        $this->assertStringNotContainsString('onclick', strtolower($out));
        $this->assertStringNotContainsString('onmouseover', strtolower($out));
        $this->assertStringNotContainsString('alert', $out);
    }

    public function test_safe_http_links_kept(): void
    {
        $out = HtmlSanitizer::sanitize('<a href="https://example.com/path">Link •</a>');
        $this->assertStringContainsString('href="https://example.com/path"', $out);
        $this->assertStringContainsString('Link •', $out);
    }
}
