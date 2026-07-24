<?php

namespace App\Services\Catalog;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storefront product image resolution — catalog product_media first, legacy product_images fallback.
 */
class CustomerProductMediaResolver
{
    /**
     * @return list<array{id: string, path: string|null, url: string|null, alt_text: string|null}>
     */
    public function resolveGallery(Product $product): array
    {
        return $this->resolveGalleryItems($product)
            ->map(fn (ProductMedia|ProductImage $item) => $this->toCustomerImageArray($item))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, path: string|null, url: string|null, alt_text: string|null}|null
     */
    public function resolvePrimary(Product $product): ?array
    {
        $items = $this->resolveGalleryItems($product);
        if ($items->isEmpty()) {
            return null;
        }

        $primary = $items->first(fn (ProductMedia|ProductImage $item) => (bool) $item->is_primary)
            ?? $items->first();

        return $primary instanceof ProductMedia || $primary instanceof ProductImage
            ? $this->toCustomerImageArray($primary)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalogEagerLoads(): array
    {
        return [
            'media' => fn ($query) => $query->images()->active()->ordered(),
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
        ];
    }

    /**
     * @return Collection<int, ProductMedia|ProductImage>
     */
    private function resolveGalleryItems(Product $product): Collection
    {
        $catalogMedia = $this->activeCatalogImages($product);
        if ($catalogMedia->isNotEmpty()) {
            return $catalogMedia;
        }

        return $this->legacyImages($product);
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    private function activeCatalogImages(Product $product): Collection
    {
        if ($product->relationLoaded('media')) {
            return $product->media
                ->filter(fn (ProductMedia $media) => $this->isActiveCatalogImage($media))
                ->sortBy([
                    fn (ProductMedia $media) => $media->is_primary ? 0 : 1,
                    fn (ProductMedia $media) => $media->sort_order,
                    fn (ProductMedia $media) => $media->created_at?->getTimestamp() ?? 0,
                ])
                ->values();
        }

        return $product->media()->images()->active()->ordered()->get();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    private function legacyImages(Product $product): Collection
    {
        if ($product->relationLoaded('images')) {
            return $product->images
                ->sortBy([
                    fn (ProductImage $image) => $image->is_primary ? 0 : 1,
                    fn (ProductImage $image) => $image->sort_order,
                    fn (ProductImage $image) => $image->created_at?->getTimestamp() ?? 0,
                ])
                ->values();
        }

        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    private function isActiveCatalogImage(ProductMedia $media): bool
    {
        if (! $media->is_active) {
            return false;
        }

        $type = $media->type instanceof ProductMediaType
            ? $media->type
            : ProductMediaType::tryFrom((string) $media->type);

        return $type === ProductMediaType::Image;
    }

    /**
     * @return array{id: string, path: string|null, url: string|null, alt_text: string|null}
     */
    private function toCustomerImageArray(ProductMedia|ProductImage $item): array
    {
        if ($item instanceof ProductMedia) {
            return [
                'id' => $item->id,
                'path' => $this->resolveMediaStoragePath($item),
                'url' => filled($item->url) ? (string) $item->url : null,
                'alt_text' => $item->alt_text,
            ];
        }

        return [
            'id' => $item->id,
            'path' => $item->path,
            'url' => filled($item->path)
                ? Storage::disk('public')->url((string) $item->path)
                : null,
            'alt_text' => $item->alt_text,
        ];
    }

    private function resolveMediaStoragePath(ProductMedia $media): ?string
    {
        $url = (string) ($media->url ?? '');
        if ($url === '') {
            return null;
        }

        $publicBase = rtrim(Storage::disk('public')->url(''), '/');
        if (Str::startsWith($url, $publicBase.'/')) {
            return ltrim(Str::after($url, $publicBase.'/'), '/');
        }

        if (Str::startsWith($url, '/storage/')) {
            return ltrim(Str::after($url, '/storage/'), '/');
        }

        return null;
    }
}
