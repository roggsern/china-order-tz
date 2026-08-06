<?php

namespace App\Support\Catalog;

/**
 * Detects and reverses UTF-8→Latin-1 mojibake produced by HtmlSanitizer before the charset fix.
 *
 * One pass of the bug maps each UTF-8 byte to a Latin-1 codepoint then re-encodes as UTF-8.
 * Reversing with UTF-8 → ISO-8859-1 recovers the original bytes. Depth 2 = apply twice.
 */
final class ProductDescriptionEncodingRepair
{
    public const CONFIRMATION_PHRASE = 'REPAIR_PRODUCT_DESCRIPTION_ENCODING';

    /** @var list<string> */
    private const MOJIBAKE_MARKERS = [
        'Ã¢', 'Ã‚', 'Ãƒ', 'Ã', 'Â', 'â€', 'â„¢', 'âœ', 'â',
    ];

    /**
     * @return array{candidate: bool, depth: int|null, repaired: string|null, reason: string}
     */
    public function evaluate(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $original = $value;
        if (trim($original) === '') {
            return [
                'candidate' => false,
                'depth' => null,
                'repaired' => null,
                'reason' => 'empty',
            ];
        }

        if (! $this->looksLikeMojibake($original)) {
            return [
                'candidate' => false,
                'depth' => null,
                'repaired' => null,
                'reason' => 'no_mojibake_markers',
            ];
        }

        $baselineScore = $this->corruptionScore($original);
        $baselineMarkers = $this->markerCount($original);
        $best = null;

        for ($depth = 1; $depth <= 2; $depth++) {
            $candidate = $this->decodeDepth($original, $depth);
            if ($candidate === null || $candidate === $original) {
                continue;
            }

            if (! mb_check_encoding($candidate, 'UTF-8')) {
                continue;
            }

            $score = $this->corruptionScore($candidate);
            $markers = $this->markerCount($candidate);

            if ($score >= $baselineScore || $markers >= $baselineMarkers) {
                continue;
            }

            if ($best === null || $score < $best['score']) {
                $best = [
                    'depth' => $depth,
                    'repaired' => $candidate,
                    'score' => $score,
                ];
            }
        }

        if ($best === null) {
            return [
                'candidate' => false,
                'depth' => null,
                'repaired' => null,
                'reason' => 'ambiguous_or_unrecoverable',
            ];
        }

        return [
            'candidate' => true,
            'depth' => $best['depth'],
            'repaired' => $best['repaired'],
            'reason' => 'recoverable',
        ];
    }

    public function looksLikeMojibake(string $value): bool
    {
        return $this->markerCount($value) > 0;
    }

    public function markerCount(string $value): int
    {
        $count = 0;
        foreach (self::MOJIBAKE_MARKERS as $marker) {
            $count += substr_count($value, $marker);
        }

        return $count;
    }

    public function corruptionScore(string $value): int
    {
        $score = $this->markerCount($value) * 10;

        foreach (['•', '–', '—', '…', '“', '”', '‘', '’', '°', '™', '✅'] as $glyph) {
            if (str_contains($value, $glyph)) {
                $score -= 3;
            }
        }

        return $score;
    }

    public function decodeDepth(string $value, int $depth): ?string
    {
        $current = $value;

        for ($i = 0; $i < $depth; $i++) {
            $converted = @mb_convert_encoding($current, 'ISO-8859-1', 'UTF-8');
            if (! is_string($converted)) {
                return null;
            }

            if (! mb_check_encoding($converted, 'UTF-8')) {
                return null;
            }

            $current = $converted;
        }

        return $current;
    }

    public function preview(string $value, int $max = 80): string
    {
        $flat = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if (mb_strlen($flat) <= $max) {
            return $flat;
        }

        return mb_substr($flat, 0, $max - 1).'…';
    }
}
