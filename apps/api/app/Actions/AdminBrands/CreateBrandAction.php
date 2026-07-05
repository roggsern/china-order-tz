<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\StoreBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Str;

class CreateBrandAction
{
    public function handle(StoreBrandRequest $request): Brand
    {
        $validated = $request->validated();

        return Brand::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Brand::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
