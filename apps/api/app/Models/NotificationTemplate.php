<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'key',
        'name',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'is_active' => 'boolean',
        ];
    }
}
