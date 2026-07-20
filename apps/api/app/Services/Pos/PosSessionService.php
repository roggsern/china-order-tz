<?php

namespace App\Services\Pos;

use App\Enums\PosSessionStatus;
use App\Enums\PosSessionVarianceType;
use App\Enums\PosVarianceReason;
use App\Events\Audit\StorePlatformAudit;
use App\Models\Admin;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Services\Stores\ActiveStoreContext;
use App\Support\Pos\PosErrors;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PosSessionService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
        private readonly PosSessionCashService $cash,
    ) {}

    public function open(
        Admin $admin,
        Store $store,
        PosTerminal $terminal,
        float $openingFloat,
        ?string $notes = null,
    ): PosSession {
        $this->storeContext->assertCanAccess($admin, $store);

        if ($openingFloat < 0) {
            throw ValidationException::withMessages([
                'opening_float' => ['Opening float cannot be negative.'],
            ]);
        }

        if ($terminal->store_id !== $store->id) {
            throw ValidationException::withMessages([
                'terminal_id' => ['Terminal does not belong to this store.'],
            ]);
        }

        if (! $terminal->is_active) {
            throw ValidationException::withMessages([
                'terminal_id' => ['Terminal is inactive.'],
            ]);
        }

        $existingCashier = PosSession::query()
            ->where('admin_id', $admin->id)
            ->where('status', PosSessionStatus::Open)
            ->first();

        if ($existingCashier !== null) {
            throw ValidationException::withMessages([
                'session' => ['Close your current POS session before opening another.'],
            ]);
        }

        $existingTerminal = PosSession::query()
            ->where('terminal_id', $terminal->id)
            ->where('status', PosSessionStatus::Open)
            ->first();

        if ($existingTerminal !== null) {
            throw ValidationException::withMessages([
                'terminal_id' => ['This terminal already has an open POS session.'],
            ]);
        }

        $session = PosSession::query()->create([
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'admin_id' => $admin->id,
            'status' => PosSessionStatus::Open,
            'opened_at' => now(),
            'opening_float' => number_format($openingFloat, 2, '.', ''),
            'cash_sales' => '0.00',
            'cash_refunds' => '0.00',
            'transaction_count' => 0,
            'notes' => $notes,
        ]);

        event(StorePlatformAudit::sessionOpened($session, $admin));
        Log::info('pos.session_open', [
            'session_id' => $session->id,
            'store_id' => $store->id,
            'admin_id' => $admin->id,
            'opening_float' => $session->opening_float,
        ]);

        return $session->fresh(['store', 'terminal', 'admin']);
    }

    /**
     * @param  array{
     *   closing_cash: float,
     *   variance_reason?: string|null,
     *   closing_notes?: string|null,
     *   notes?: string|null
     * }  $payload
     */
    public function close(PosSession $session, Admin $admin, array $payload): PosSession
    {
        $this->assertCanClose($session, $admin);

        if (! $session->isOpen()) {
            PosErrors::sessionClosed();
        }

        if (! array_key_exists('closing_cash', $payload) || $payload['closing_cash'] === null) {
            throw ValidationException::withMessages([
                'closing_cash' => ['Actual cash count is required to close the session.'],
            ]);
        }

        $actual = number_format((float) $payload['closing_cash'], 2, '.', '');
        if (bccomp($actual, '0.00', 2) < 0) {
            throw ValidationException::withMessages([
                'closing_cash' => ['Actual cash count cannot be negative.'],
            ]);
        }

        $snapshot = $this->cash->snapshotForClose($session);
        $expected = $snapshot['expected_cash'];
        $varianceAmount = bcsub($actual, $expected, 2);
        $varianceType = PosSessionVarianceType::fromDifference($varianceAmount);

        $reason = null;
        if ($varianceType !== PosSessionVarianceType::Balanced) {
            $rawReason = $payload['variance_reason'] ?? null;
            $reason = PosVarianceReason::tryFrom((string) $rawReason)?->value
                ?? PosVarianceReason::Other->value;
        }

        $session->forceFill([
            'status' => PosSessionStatus::Closed,
            'closed_at' => now(),
            'closing_cash' => $actual,
            'expected_cash' => $expected,
            'cash_sales' => $snapshot['cash_sales'],
            'cash_refunds' => $snapshot['cash_refunds'],
            'payment_breakdown' => $snapshot['payment_breakdown'],
            'transaction_count' => $snapshot['transaction_count'],
            'variance_amount' => $varianceAmount,
            'variance_type' => $varianceType,
            'variance_reason' => $reason,
            'closing_notes' => $payload['closing_notes'] ?? $payload['notes'] ?? null,
            'notes' => $payload['notes'] ?? $session->notes,
        ])->save();

        event(StorePlatformAudit::sessionClosed($session->fresh(), $admin));

        if ($varianceType !== PosSessionVarianceType::Balanced) {
            event(StorePlatformAudit::varianceDetected($session->fresh(), $admin));
        }

        Log::info('pos.session_close', [
            'session_id' => $session->id,
            'store_id' => $session->store_id,
            'admin_id' => $admin->id,
            'expected_cash' => $expected,
            'closing_cash' => $actual,
            'variance_type' => $varianceType->value,
        ]);

        return $session->fresh(['store', 'terminal', 'admin']);
    }

    public function updateFloat(PosSession $session, Admin $admin, float $openingFloat, ?string $notes = null): PosSession
    {
        $this->assertCanClose($session, $admin);

        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['Cannot update float on a closed session.'],
            ]);
        }

        if ($openingFloat < 0) {
            throw ValidationException::withMessages([
                'opening_float' => ['Opening float cannot be negative.'],
            ]);
        }

        $previous = number_format((float) $session->opening_float, 2, '.', '');
        $next = number_format($openingFloat, 2, '.', '');

        $session->forceFill([
            'opening_float' => $next,
            'notes' => $notes ?? $session->notes,
        ])->save();

        event(StorePlatformAudit::floatUpdated($session->fresh(), $previous, $next, $admin));

        return $session->fresh(['store', 'terminal', 'admin']);
    }

    public function currentOpen(Admin $admin): ?PosSession
    {
        return PosSession::query()
            ->with(['store', 'terminal', 'admin'])
            ->where('admin_id', $admin->id)
            ->where('status', PosSessionStatus::Open)
            ->first();
    }

    /**
     * @return array{session: PosSession|null, summary: array<string, mixed>|null}
     */
    public function cashierDashboard(Admin $admin): array
    {
        $session = $this->currentOpen($admin);

        return [
            'session' => $session,
            'summary' => $session ? $this->cash->summarize($session) : null,
        ];
    }

    public function show(Admin $admin, PosSession $session): PosSession
    {
        $this->assertCanView($admin, $session);

        return $session->load(['store', 'terminal', 'admin']);
    }

    /**
     * Manager / super-admin session listing with store isolation.
     *
     * @param  array{store_id?: string|null, status?: string|null, from?: string|null, to?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, PosSession>
     */
    public function listForManager(Admin $admin, array $filters = []): LengthAwarePaginator
    {
        $query = PosSession::query()
            ->with(['store', 'terminal', 'admin'])
            ->latest('opened_at');

        if (! $admin->is_super_admin) {
            $storeIds = $this->storeContext->assignedStores($admin)->pluck('id');
            $query->whereIn('store_id', $storeIds);

            // Store cashiers only see their own sessions; master cashier sees assigned stores.
            if ($admin->isStoreCashier() && ! $admin->isMasterCashier()) {
                $query->where('admin_id', $admin->id);
            }
        }

        if (! empty($filters['store_id'])) {
            $store = Store::query()->findOrFail($filters['store_id']);
            $this->storeContext->assertCanAccess($admin, $store);
            $query->where('store_id', $store->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('opened_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('opened_at', '<=', $filters['to']);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryPayload(PosSession $session): array
    {
        return $this->cash->summarize($session);
    }

    private function assertCanClose(PosSession $session, Admin $admin): void
    {
        if ($admin->is_super_admin) {
            return;
        }

        if ($session->admin_id === $admin->id) {
            $this->storeContext->assertCanAccess($admin, $session->store()->firstOrFail());

            return;
        }

        throw ValidationException::withMessages([
            'session' => ['You can only close your own POS session.'],
        ]);
    }

    private function assertCanView(Admin $admin, PosSession $session): void
    {
        if ($admin->is_super_admin) {
            return;
        }

        $this->storeContext->assertCanAccess($admin, $session->store()->firstOrFail());

        if ($admin->isStoreCashier() && ! $admin->isMasterCashier() && $session->admin_id !== $admin->id) {
            throw ValidationException::withMessages([
                'session' => ['You can only view your own POS sessions.'],
            ]);
        }
    }
}
