<?php

namespace App\Http\Requests\Admin\CMS;

use App\Models\CmsNavigationShell;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCmsNavigationItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsNavigationShell $shell */
        $shell = $this->route('navigationShell') ?? $this->route('shell');

        return $this->user()?->can('update', $shell) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.position' => ['required', 'integer', 'min:0'],
            'items.*.parent_id' => ['nullable', 'uuid'],
        ];
    }
}
