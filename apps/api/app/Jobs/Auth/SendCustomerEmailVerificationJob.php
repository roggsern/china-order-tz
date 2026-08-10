<?php

namespace App\Jobs\Auth;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends customer email-verification notification off the HTTP request path.
 * Registration must succeed even when SMTP/notification delivery fails.
 */
class SendCustomerEmailVerificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Bound hung SMTP so workers recycle instead of blocking forever. */
    public int $timeout = 60;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        public readonly string $userId,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            Log::warning('auth.email_verification_job.user_missing', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('auth.email_verification_job.failed', [
                'user_id' => $this->userId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            // Do not rethrow — account already exists; customer can resend verification.
        }
    }
}
