<?php

namespace App\Http\Requests\Admin\CMS;

use App\Models\CmsFeaturedContent;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCmsFeaturedContentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', CmsFeaturedContent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'featured_content_ids' => ['required', 'array', 'min:1'],
            'featured_content_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
