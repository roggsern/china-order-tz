<?php

namespace Tests\Unit\Support\Catalog;

use App\Support\Catalog\ProductDescriptionEncodingRepair;
use Tests\TestCase;

class ProductDescriptionEncodingRepairTest extends TestCase
{
    public function test_recovers_single_pass_mojibake(): void
    {
        // Simulate pre-fix sanitizer corruption by reversing a known UTF-8 glyph through Latin-1 remapping.
        $original = 'Hello • world …';
        $corrupt = $this->simulateLegacySanitizerMojibake($original);

        $this->assertNotSame($original, $corrupt);
        $this->assertTrue(str_contains($corrupt, 'â') || str_contains($corrupt, 'Â') || str_contains($corrupt, 'Ã'));

        $repair = new ProductDescriptionEncodingRepair;
        $result = $repair->evaluate($corrupt);

        $this->assertTrue($result['candidate']);
        $this->assertSame(1, $result['depth']);
        $this->assertSame($original, $result['repaired']);
    }

    public function test_recovers_double_pass_mojibake(): void
    {
        $original = 'Features – “quoted” …';
        $once = $this->simulateLegacySanitizerMojibake($original);
        $twice = $this->simulateLegacySanitizerMojibake($once);

        $repair = new ProductDescriptionEncodingRepair;
        $result = $repair->evaluate($twice);

        $this->assertTrue($result['candidate']);
        $this->assertSame(2, $result['depth']);
        $this->assertSame($original, $result['repaired']);
    }

    public function test_skips_clean_text(): void
    {
        $repair = new ProductDescriptionEncodingRepair;
        $result = $repair->evaluate('Clean ASCII and Unicode • – …');

        $this->assertFalse($result['candidate']);
        $this->assertSame('no_mojibake_markers', $result['reason']);
    }

    /**
     * Reconstruct the pre-fix libxml bug: each UTF-8 byte becomes a Latin-1 char, then UTF-8 again.
     */
    private function simulateLegacySanitizerMojibake(string $utf8): string
    {
        $bytes = unpack('C*', $utf8);
        $this->assertIsArray($bytes);

        $latin1 = '';
        foreach ($bytes as $byte) {
            $latin1 .= chr($byte);
        }

        // Interpret those Latin-1 codepoints as Unicode and encode as UTF-8.
        return mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1');
    }
}
