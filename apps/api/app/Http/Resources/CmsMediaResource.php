<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Media */
class CmsMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = null;
        try {
            if ($this->path) {
                $url = Storage::disk($this->disk ?: 'public')->url($this->path);
            }
        } catch (\Throwable) {
            $url = null;
        }

        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'alt_text' => $this->alt_text,
            'url' => $url,
        ];
    }
}
