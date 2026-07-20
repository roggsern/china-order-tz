<?php

namespace App\Actions\AdminDepartments;

use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Support\Str;

class UpdateDepartmentAction
{
    public function handle(UpdateDepartmentRequest $request, Department $department): Department
    {
        $validated = $request->validated();

        $data = ['name' => $validated['name']];

        if (array_key_exists('slug', $validated) && is_string($validated['slug']) && trim($validated['slug']) !== '') {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($validated['slug']), $department->id);
        } elseif ($validated['name'] !== $department->name) {
            $data['slug'] = $this->generateUniqueSlug($validated['name'], $department->id);
        }

        if (array_key_exists('icon', $validated)) {
            $data['icon'] = $validated['icon'];
        }

        if (array_key_exists('image', $validated)) {
            $data['image'] = $validated['image'];
        }

        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $data['sort_order'] = $validated['sort_order'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = $validated['is_active'];
        }

        $department->update($data);

        return $department->fresh();
    }

    private function generateUniqueSlug(string $name, string $ignoreDepartmentId): string
    {
        $slug = Str::slug($name);
        $original = $slug !== '' ? $slug : 'department';

        return $this->ensureUniqueSlug($original, $ignoreDepartmentId);
    }

    private function ensureUniqueSlug(string $original, string $ignoreDepartmentId): string
    {
        $slug = $original !== '' ? $original : 'department';
        $candidate = $slug;
        $counter = 1;

        while (
            Department::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $ignoreDepartmentId)
                ->exists()
        ) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
