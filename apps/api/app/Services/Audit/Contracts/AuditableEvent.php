<?php

namespace App\Services\Audit\Contracts;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;

/**
 * Laravel Events that the Audit Platform listens to must implement this.
 * Business modules dispatch events — they never write activity logs.
 */
interface AuditableEvent
{
    public function eventType(): ActivityEventType;

    public function action(): string;

    public function actorType(): ActivityActorType;

    public function actorId(): ?string;

    public function subjectType(): ?string;

    public function subjectId(): ?string;

    public function description(): string;

    /** @return array<string, mixed>|null */
    public function oldValues(): ?array;

    /** @return array<string, mixed>|null */
    public function newValues(): ?array;

    /** @return array<string, mixed>|null */
    public function metadata(): ?array;

    public function ipAddress(): ?string;

    public function userAgent(): ?string;
}
