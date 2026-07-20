<?php

namespace Database\Seeders;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductMedia;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds Product Media for Product Core demo products.
 */
class ProductMediaSeeder extends Seeder
{
    /**
     * @return array<string, list<string>>
     */
    public static function productAssetMap(): array
    {
        return [
            'samsung-galaxy-s25' => ['phone.jpg', 'laptop.jpg', 'watch.jpg'],
            'iphone-17' => ['phone.jpg', 'watch.jpg'],
            'tecno-camon' => ['phone.jpg'],
            'xiaomi-redmi-note' => ['phone.jpg', 'headphones.jpg'],
            'google-pixel' => ['phone.jpg'],
            'jbl-eon' => ['headphones.jpg', 'table.jpg', 'chair.jpg'],
            'yamaha-mg-mixer' => ['headphones.jpg', 'table.jpg'],
            'rcf-speaker' => ['headphones.jpg', 'chair.jpg'],
            'bose-s1-pro' => ['headphones.jpg'],
            'qsc-k-series' => ['headphones.jpg', 'table.jpg'],
            'nike-air-max' => ['shoes.jpg', 'bag.jpg'],
            'levis-jeans' => ['dress.jpg', 'bag.jpg'],
            'zara-dress' => ['dress.jpg', 'perfume.jpg'],
            'adidas-ultraboost' => ['shoes.jpg'],
            'puma-rs-x' => ['shoes.jpg', 'bag.jpg'],
            'hm-casual-tee' => ['dress.jpg'],
            'gucci-loafer' => ['shoes.jpg', 'watch.jpg'],
            'nike-dri-fit-polo' => ['dress.jpg', 'shoes.jpg'],
            'zara-mini-dress' => ['dress.jpg', 'perfume.jpg'],
        ];
    }

    public function run(): void
    {
        $this->ensureDemoAssets();

        foreach (self::productAssetMap() as $slug => $filenames) {
            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null) {
                continue;
            }

            foreach ($filenames as $index => $filename) {
                $path = DemoProductImageLibrary::publicPath($filename);
                $url = Storage::disk('public')->url($path);

                ProductMedia::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'url' => $url,
                        'type' => ProductMediaType::Image,
                    ],
                    [
                        'thumbnail_url' => $url,
                        'alt_text' => $product->name.' image '.($index + 1),
                        'title' => $product->name.($index === 0 ? ' — Primary' : ' — Gallery'),
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                        'is_active' => true,
                    ],
                );
            }

            // Sample video for a couple of flagship products.
            if (in_array($slug, ['samsung-galaxy-s25', 'jbl-eon', 'nike-air-max'], true)) {
                $videoUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
                ProductMedia::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'type' => ProductMediaType::Video,
                        'url' => $videoUrl,
                    ],
                    [
                        'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                        'alt_text' => $product->name.' video',
                        'title' => $product->name.' — Product video',
                        'sort_order' => 100,
                        'is_primary' => false,
                        'is_active' => true,
                    ],
                );
            }
        }

        // Fallback: any Product Core product without media gets one demo image.
        Product::query()
            ->whereDoesntHave('media')
            ->where('is_demo', true)
            ->limit(40)
            ->get()
            ->each(function (Product $product) {
                $path = DemoProductImageLibrary::randomPath();
                $url = Storage::disk('public')->url($path);

                ProductMedia::query()->create([
                    'product_id' => $product->id,
                    'type' => ProductMediaType::Image,
                    'url' => $url,
                    'thumbnail_url' => $url,
                    'alt_text' => $product->name,
                    'title' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            });
    }

    private function ensureDemoAssets(): void
    {
        $source = DemoProductImageLibrary::assetsDirectory();
        $target = DemoProductImageLibrary::storageDirectory();

        if (! is_dir($target)) {
            mkdir($target, 0775, true);
        }

        foreach (DemoProductImageLibrary::FILENAMES as $filename) {
            $from = $source.DIRECTORY_SEPARATOR.$filename;
            $to = $target.DIRECTORY_SEPARATOR.$filename;

            if (is_file($from) && ! is_file($to)) {
                copy($from, $to);
            }
        }
    }
}
