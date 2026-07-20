<?php

namespace App\Http\Requests\Admin\CMS;

use App\Models\CmsHomepageLayout;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCmsHomepageSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CmsHomepageLayout $layout */
        $layout = $this->route('layout');

        return $this->user()?->can('reorder', $layout) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_ids' => ['required', 'array', 'min:1'],
            'section_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
