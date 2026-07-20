<?php

namespace Database\Support;

final class DemoProductImageLibrary
{
    /** @var list<string> */
    public const FILENAMES = [
        'phone.jpg',
        'laptop.jpg',
        'shoes.jpg',
        'bag.jpg',
        'dress.jpg',
        'watch.jpg',
        'perfume.jpg',
        'chair.jpg',
        'table.jpg',
        'headphones.jpg',
    ];

    public static function assetsDirectory(): string
    {
        return database_path('assets/demo-products');
    }

    public static function storageDirectory(): string
    {
        return storage_path('app/public/demo-products');
    }

    public static function publicPath(string $filename): string
    {
        return 'demo-products/'.$filename;
    }

    public static function randomPath(): string
    {
        $filename = fake()->randomElement(self::FILENAMES);

        return self::publicPath($filename);
    }

    /** @return list<string> */
    public static function publicPaths(): array
    {
        return array_map(
            fn (string $filename): string => self::publicPath($filename),
            self::FILENAMES,
        );
    }
}
