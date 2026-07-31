<?php

namespace App\Services\Storefront;

use App\Models\StorefrontSession;
use App\Models\StorefrontVisitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitorIdentityService
{
    /**
     * @return array{visitor_id: string, session_id: string, visitor_uuid: string}
     */
    public function identify(?string $visitorUuid, ?string $sessionId, ?User $user = null): array
    {
        $now = now();
        $normalizedVisitorUuid = $this->normalizeVisitorUuid($visitorUuid);

        return DB::transaction(function () use ($normalizedVisitorUuid, $sessionId, $user, $now) {
            $visitor = $this->resolveVisitor($normalizedVisitorUuid, $now);
            $session = $this->resolveSession($visitor, $sessionId, $user, $now);

            if ($user !== null) {
                $this->attachAuthenticatedCustomer($session, $user);
            }

            return [
                'visitor_id' => $visitor->id,
                'session_id' => $session->id,
                'visitor_uuid' => $visitor->visitor_uuid,
            ];
        });
    }

    public function resolveVisitor(string $visitorUuid, ?\DateTimeInterface $seenAt = null): StorefrontVisitor
    {
        $seenAt ??= now();

        $visitor = StorefrontVisitor::query()
            ->where('visitor_uuid', $visitorUuid)
            ->first();

        if ($visitor === null) {
            return StorefrontVisitor::query()->create([
                'visitor_uuid' => $visitorUuid,
                'first_seen_at' => $seenAt,
                'last_seen_at' => $seenAt,
            ]);
        }

        $visitor->forceFill(['last_seen_at' => $seenAt])->save();

        return $visitor->refresh();
    }

    public function resolveSession(
        StorefrontVisitor $visitor,
        ?string $sessionId,
        ?User $user,
        ?\DateTimeInterface $activityAt = null,
    ): StorefrontSession {
        $activityAt ??= now();
        $timeoutMinutes = max(1, (int) config('storefront.visitor_session_timeout_minutes', 30));
        $cutoff = now()->subMinutes($timeoutMinutes);

        if ($sessionId !== null) {
            $existing = StorefrontSession::query()
                ->where('id', $sessionId)
                ->where('visitor_id', $visitor->id)
                ->whereNull('ended_at')
                ->first();

            if ($existing !== null && $existing->last_activity_at->greaterThanOrEqualTo($cutoff)) {
                $existing->forceFill(['last_activity_at' => $activityAt])->save();

                return $existing->refresh();
            }

            if ($existing !== null) {
                $existing->forceFill(['ended_at' => $activityAt])->save();
            }
        }

        return $this->createSession($visitor, $user, $activityAt);
    }

    public function createSession(
        StorefrontVisitor $visitor,
        ?User $user = null,
        ?\DateTimeInterface $startedAt = null,
    ): StorefrontSession {
        $startedAt ??= now();

        return StorefrontSession::query()->create([
            'visitor_id' => $visitor->id,
            'user_id' => $user?->id,
            'started_at' => $startedAt,
            'ended_at' => null,
            'last_activity_at' => $startedAt,
        ]);
    }

    public function attachAuthenticatedCustomer(StorefrontSession $session, User $user): StorefrontSession
    {
        if ($session->user_id === $user->id) {
            return $session;
        }

        $session->forceFill(['user_id' => $user->id])->save();

        return $session->refresh();
    }

    private function normalizeVisitorUuid(?string $visitorUuid): string
    {
        $candidate = is_string($visitorUuid) ? trim($visitorUuid) : '';

        if ($candidate !== '' && Str::isUuid($candidate)) {
            return strtolower($candidate);
        }

        return (string) Str::uuid();
    }
}
