<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Str;

class UpdateBrandAction
{
    public function handle(UpdateBrandRequest $request, Brand $brand): Brand
    {
        $validated = $request->validated();

        $data = ['name' => $validated['name']];

        if ($validated['name'] !== $brand->name) {
            $data['slug'] = $this->generateUniqueSlug($validated['name'], $brand->id);
        }

        $brand->update($data);

        return $brand->fresh();
    }

    private function generateUniqueSlug(string $name, string $ignoreBrandId): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (
            Brand::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreBrandId)
                ->exists()
        ) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
