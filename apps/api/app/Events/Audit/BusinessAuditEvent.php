<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Services\Audit\Contracts\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base auditable business event. Modules dispatch subclasses; Audit Listener records.
 */
abstract class BusinessAuditEvent implements AuditableEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly ActivityEventType $type,
        public readonly string $actionLabel,
        public readonly ActivityActorType $actor,
        public readonly ?string $actorIdentifier,
        public readonly ?string $subjectClass,
        public readonly ?string $subjectIdentifier,
        public readonly string $summary,
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly ?array $meta = null,
        public readonly ?string $ip = null,
        public readonly ?string $ua = null,
    ) {}

    public function eventType(): ActivityEventType
    {
        return $this->type;
    }

    public function action(): string
    {
        return $this->actionLabel;
    }

    public function actorType(): ActivityActorType
    {
        return $this->actor;
    }

    public function actorId(): ?string
    {
        return $this->actorIdentifier;
    }

    public function subjectType(): ?string
    {
        return $this->subjectClass;
    }

    public function subjectId(): ?string
    {
        return $this->subjectIdentifier;
    }

    public function description(): string
    {
        return $this->summary;
    }

    public function oldValues(): ?array
    {
        return $this->before;
    }

    public function newValues(): ?array
    {
        return $this->after;
    }

    public function metadata(): ?array
    {
        return $this->meta;
    }

    public function ipAddress(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->ua;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    protected static function make(
        ActivityEventType $type,
        ActivityActorType $actorType,
        ?string $actorId,
        ?string $subjectType,
        ?string $subjectId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $action = null,
    ): static {
        return new static(
            type: $type,
            actionLabel: $action ?? $type->defaultAction(),
            actor: $actorType,
            actorIdentifier: $actorId,
            subjectClass: $subjectType,
            subjectIdentifier: $subjectId,
            summary: $description,
            before: $oldValues,
            after: $newValues,
            meta: $metadata,
            ip: $ipAddress ?? (request()?->ip()),
            ua: $userAgent ?? (request()?->userAgent()),
        );
    }
}
