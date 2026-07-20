<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'disk',
        'path',
        'filename',
        'mime',
        'size',
        'alt_text',
        'mediable_type',
        'mediable_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
