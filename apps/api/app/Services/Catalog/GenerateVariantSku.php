<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class GenerateVariantSku
{
    /**
     * @param  list<string>  $optionLabels
     */
    public function handle(Product $product, array $optionLabels = [], int $sequence = 1): string
    {
        $base = strtoupper(Str::slug($product->sku ?: $product->slug ?: $product->name, '-'));
        $base = $base !== '' ? $base : 'VAR';

        $suffix = collect($optionLabels)
            ->filter()
            ->map(fn (string $label) => strtoupper(Str::slug($label, '')))
            ->filter()
            ->map(fn (string $slug) => substr($slug, 0, 8))
            ->implode('-');

        $candidate = $suffix !== ''
            ? "{$base}-{$suffix}"
            : sprintf('%s-%02d', $base, $sequence);

        if (! ProductVariant::withTrashed()->where('sku', $candidate)->exists()) {
            return $candidate;
        }

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $withSeq = sprintf('%s-%02d', $candidate, $attempt);
            if (! ProductVariant::withTrashed()->where('sku', $withSeq)->exists()) {
                return $withSeq;
            }
        }

        return sprintf('%s-%s', $base, strtoupper(Str::random(6)));
    }
}
