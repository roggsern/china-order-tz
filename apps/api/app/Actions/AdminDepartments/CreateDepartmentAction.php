<?php

namespace App\Actions\AdminDepartments;

use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Models\Department;
use Illuminate\Support\Str;

class CreateDepartmentAction
{
    public function handle(StoreDepartmentRequest $request): Department
    {
        $validated = $request->validated();

        $slugSource = isset($validated['slug']) && is_string($validated['slug']) && trim($validated['slug']) !== ''
            ? $validated['slug']
            : $validated['name'];

        return Department::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($slugSource),
            'icon' => $validated['icon'] ?? null,
            'image' => $validated['image'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }

    private function generateUniqueSlug(string $value): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'department';
        $candidate = $original;
        $counter = 1;

        while (Department::where('slug', $candidate)->exists()) {
            $candidate = $original.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
