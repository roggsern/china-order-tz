<?php

namespace App\Http\Requests\Admin\CMS;

use App\Models\CmsHeroSlide;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCmsHeroSlidesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', CmsHeroSlide::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slide_ids' => ['required', 'array', 'min:1'],
            'slide_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
