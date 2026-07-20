<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncBrandCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }
}
