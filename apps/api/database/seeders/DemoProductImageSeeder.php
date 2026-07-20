<?php

namespace Database\Seeders;

use App\Models\ProductImage;
use Database\Support\DemoProductImageLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DemoProductImageSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDirectory = DemoProductImageLibrary::assetsDirectory();
        $targetDirectory = DemoProductImageLibrary::storageDirectory();

        if (! File::isDirectory($sourceDirectory)) {
            $this->command?->warn('Demo product image assets are missing at database/assets/demo-products.');

            return;
        }

        File::ensureDirectoryExists($targetDirectory);

        foreach (DemoProductImageLibrary::FILENAMES as $filename) {
            $source = $sourceDirectory.DIRECTORY_SEPARATOR.$filename;
            $target = $targetDirectory.DIRECTORY_SEPARATOR.$filename;

            if (! File::exists($source)) {
                $this->command?->warn("Missing demo asset: {$filename}");

                continue;
            }

            if (! File::exists($target) || File::lastModified($source) > File::lastModified($target)) {
                File::copy($source, $target);
            }
        }

        ProductImage::query()->each(function (ProductImage $image): void {
            if (! $image->path || Storage::disk('public')->exists($image->path)) {
                return;
            }

            $image->update([
                'path' => DemoProductImageLibrary::randomPath(),
            ]);
        });
    }
}
