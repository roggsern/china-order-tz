<?php

namespace App\Services\Catalog;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\ProductMedia\VariantMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storefront product media resolution — catalog product_media is SSoT.
 * Legacy product_images are read-only fallback when no active catalog images exist.
 * When a variant is provided, delegates to VariantMediaResolver (variant media → product media fallback).
 */
class CustomerProductMediaResolver
{
    public function __construct(
        private readonly VariantMediaResolver $variantMediaResolver,
    ) {}

    /**
     * @return list<array{id: string, url: string, thumbnail_url: string|null, title: string|null, alt_text: string|null, sort_order: int}>
     */
    public function resolveVideos(Product $product): array
    {
        return $this->activeCatalogVideos($product)
            ->map(fn (ProductMedia $media) => $this->toCustomerVideoArray($media))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, path: string|null, url: string|null, alt_text: string|null}>
     */
    public function resolveGallery(Product $product, ?ProductVariant $variant = null): array
    {
        return $this->resolveGalleryItems($product, $variant)
            ->map(fn (ProductMedia|ProductImage $item) => $this->toCustomerImageArray($item))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, path: string|null, url: string|null, alt_text: string|null}|null
     */
    public function resolvePrimary(Product $product, ?ProductVariant $variant = null): ?array
    {
        $items = $this->resolveGalleryItems($product, $variant);
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
     * @return list<array{
     *     id: string,
     *     path: string|null,
     *     url: string|null,
     *     thumbnail_url: string|null,
     *     alt_text: string|null,
     *     sort_order: int,
     *     is_primary: bool
     * }>
     */
    public function resolveAdminGallery(Product $product, ?ProductVariant $variant = null): array
    {
        return $this->resolveGalleryItems($product, $variant)
            ->map(fn (ProductMedia|ProductImage $item) => $this->toAdminImageArray($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalogEagerLoads(): array
    {
        return [
            'media' => fn ($query) => $query->images()->active()->ordered(),
            'videos' => fn ($query) => $query->active(),
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function variantMediaEagerLoads(): array
    {
        return [
            'media' => fn ($query) => $query->images()->active()->ordered(),
        ];
    }

    /**
     * @return Collection<int, ProductMedia|ProductImage>
     */
    private function resolveGalleryItems(Product $product, ?ProductVariant $variant = null): Collection
    {
        if ($variant !== null) {
            $variantImages = $this->variantMediaResolver
                ->resolve($product, $variant)
                ->filter(fn (ProductMedia $media) => $this->isActiveCatalogImage($media))
                ->values();

            if ($variantImages->isNotEmpty()) {
                return $variantImages;
            }
        }

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
            return $this->orderPrimaryThenSortOrder(
                $product->media->filter(fn (ProductMedia $media) => $this->isActiveCatalogImage($media)),
            );
        }

        return $product->media()->images()->active()->ordered()->get();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    private function legacyImages(Product $product): Collection
    {
        if ($product->relationLoaded('images')) {
            return $this->orderPrimaryThenSortOrder($product->images);
        }

        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    private function activeCatalogVideos(Product $product): Collection
    {
        if ($product->relationLoaded('videos')) {
            return $this->orderBySortOrder(
                $product->videos->filter(fn (ProductMedia $media) => $this->isActiveCatalogVideo($media)),
            );
        }

        return $product->videos()->active()->get();
    }

    /**
     * Deterministic gallery order for eager-loaded collections.
     * Laravel sortBy([...closures]) treats closures as two-arg comparators — use attribute tuples instead.
     *
     * @template TModel of ProductMedia|ProductImage
     *
     * @param  Collection<int, TModel>  $items
     * @return Collection<int, TModel>
     */
    private function orderPrimaryThenSortOrder(Collection $items): Collection
    {
        return $items
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, ProductMedia>  $items
     * @return Collection<int, ProductMedia>
     */
    private function orderBySortOrder(Collection $items): Collection
    {
        return $items
            ->sortBy([
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();
    }

    private function isActiveCatalogImage(ProductMedia $media): bool
    {
        if (! $media->is_active) {
            return false;
        }

        return $this->resolveMediaType($media) === ProductMediaType::Image;
    }

    private function isActiveCatalogVideo(ProductMedia $media): bool
    {
        if (! $media->is_active) {
            return false;
        }

        return $this->resolveMediaType($media) === ProductMediaType::Video;
    }

    private function resolveMediaType(ProductMedia $media): ?ProductMediaType
    {
        return $media->type instanceof ProductMediaType
            ? $media->type
            : ProductMediaType::tryFrom((string) $media->type);
    }

    /**
     * @return array{id: string, url: string, thumbnail_url: string|null, title: string|null, alt_text: string|null, sort_order: int}
     */
    private function toCustomerVideoArray(ProductMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => (string) $media->url,
            'thumbnail_url' => filled($media->thumbnail_url) ? (string) $media->thumbnail_url : null,
            'title' => filled($media->title) ? (string) $media->title : null,
            'alt_text' => $media->alt_text,
            'sort_order' => (int) $media->sort_order,
        ];
    }

    /**
     * Eager loads for admin product detail/list gallery resolution.
     *
     * @return array<string, mixed>
     */
    public static function adminProductEagerLoads(): array
    {
        return [
            'media' => fn ($query) => $query->images()->active()->ordered(),
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
        ];
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

    /**
     * @return array{
     *     id: string,
     *     path: string|null,
     *     url: string|null,
     *     thumbnail_url: string|null,
     *     alt_text: string|null,
     *     sort_order: int,
     *     is_primary: bool
     * }
     */
    private function toAdminImageArray(ProductMedia|ProductImage $item): array
    {
        if ($item instanceof ProductMedia) {
            $path = $this->resolveMediaStoragePath($item);
            $url = filled($item->url) ? (string) $item->url : null;
            $thumbnail = filled($item->thumbnail_url) ? (string) $item->thumbnail_url : $url;

            return [
                'id' => $item->id,
                'path' => $path,
                'url' => $url,
                'thumbnail_url' => $thumbnail,
                'alt_text' => $item->alt_text,
                'sort_order' => (int) $item->sort_order,
                'is_primary' => (bool) $item->is_primary,
            ];
        }

        $url = filled($item->path)
            ? Storage::disk('public')->url((string) $item->path)
            : null;

        return [
            'id' => $item->id,
            'path' => $item->path,
            'url' => $url,
            'thumbnail_url' => $url,
            'alt_text' => $item->alt_text,
            'sort_order' => (int) $item->sort_order,
            'is_primary' => (bool) $item->is_primary,
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
