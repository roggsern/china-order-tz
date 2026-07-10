<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\NmbReplayGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NmbReplayGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(string $sessionId = 'SESSION000123', string $reference = 'PAY-2026-000001'): array
    {
        return [
            'result' => 'SUCCESS',
            'session' => ['id' => $sessionId],
            'order' => ['id' => $reference],
        ];
    }

    public function test_remember_marks_callback_as_duplicate(): void
    {
        Cache::flush();

        $guard = app(NmbReplayGuard::class);
        $payload = $this->callbackPayload();

        $this->assertFalse($guard->isDuplicate($payload));

        $guard->remember($payload);

        $this->assertTrue($guard->isDuplicate($payload));
    }

    public function test_duplicate_detection_returns_false_without_identifiers(): void
    {
        Cache::flush();

        $guard = app(NmbReplayGuard::class);

        $this->assertFalse($guard->isDuplicate(['result' => 'SUCCESS']));
    }
}
