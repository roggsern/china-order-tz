<?php

namespace App\Models;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only activity log. Never update or delete.
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;
    use HasUuidPrimaryKey;

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'event_type',
        'action',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ActivityEventType::class,
            'actor_type' => ActivityActorType::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve actor from actor_type alias (admin / system / customer).
     */
    public function resolveActor(): Admin|User|null
    {
        if ($this->actor_id === null) {
            return null;
        }

        return match ($this->actor_type) {
            ActivityActorType::Admin => Admin::query()->find($this->actor_id),
            ActivityActorType::Customer => User::query()->find($this->actor_id),
            ActivityActorType::System => null,
        };
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Activity logs are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Activity logs are append-only and cannot be deleted.');
        });
    }
}
