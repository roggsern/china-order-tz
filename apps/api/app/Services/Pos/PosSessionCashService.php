<?php

namespace App\Services\Pos;

use App\Enums\PaymentStatus;
use App\Enums\PosPaymentHandler;
use App\Enums\PosSessionVarianceType;
use App\Enums\RefundTransactionStatus;
use App\Models\Payment;
use App\Models\PaymentMethodDefinition;
use App\Models\PosSession;
use App\Models\RefundTransaction;
use Illuminate\Support\Collection;

/**
 * Derives session cash accountability from Order / Payment engines.
 * Payment methods are resolved dynamically — never hardcoded.
 */
class PosSessionCashService
{
    /**
     * @return array{
     *   opening_float: string,
     *   cash_sales: string,
     *   cash_refunds: string,
     *   expected_cash: string,
     *   transaction_count: int,
     *   average_sale: string,
     *   total_sales: string,
     *   payment_breakdown: list<array{code: string, name: string, amount: string, count: int, is_cash: bool}>,
     *   status: string,
     *   closing_cash: string|null,
     *   variance_amount: string|null,
     *   variance_type: string|null,
     *   variance_reason: string|null
     * }
     */
    public function summarize(PosSession $session): array
    {
        $totals = $this->computeLiveTotals($session);

        $opening = number_format((float) $session->opening_float, 2, '.', '');
        $expected = bcadd(bcsub($opening, $totals['cash_refunds'], 2), $totals['cash_sales'], 2);

        $closing = $session->closing_cash !== null
            ? number_format((float) $session->closing_cash, 2, '.', '')
            : null;

        $varianceAmount = $session->variance_amount !== null
            ? number_format((float) $session->variance_amount, 2, '.', '')
            : ($closing !== null ? bcsub($closing, $expected, 2) : null);

        $varianceType = $session->variance_type instanceof PosSessionVarianceType
            ? $session->variance_type->value
            : ($session->variance_type
                ?? ($varianceAmount !== null ? PosSessionVarianceType::fromDifference($varianceAmount)->value : null));

        return [
            'opening_float' => $opening,
            'cash_sales' => $totals['cash_sales'],
            'cash_refunds' => $totals['cash_refunds'],
            'expected_cash' => $session->isOpen()
                ? $expected
                : number_format((float) ($session->expected_cash ?? $expected), 2, '.', ''),
            'transaction_count' => $totals['transaction_count'],
            'average_sale' => $totals['average_sale'],
            'total_sales' => $totals['total_sales'],
            'payment_breakdown' => $totals['payment_breakdown'],
            'status' => $session->status instanceof \BackedEnum
                ? $session->status->value
                : (string) $session->status,
            'closing_cash' => $closing,
            'variance_amount' => $varianceAmount,
            'variance_type' => $varianceType,
            'variance_reason' => $session->variance_reason,
            'current_cash' => $expected,
        ];
    }

    /**
     * @return array{
     *   cash_sales: string,
     *   cash_refunds: string,
     *   total_sales: string,
     *   transaction_count: int,
     *   average_sale: string,
     *   payment_breakdown: list<array{code: string, name: string, amount: string, count: int, is_cash: bool}>
     * }
     */
    public function computeLiveTotals(PosSession $session): array
    {
        $methods = $this->posMethodsByCode();
        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', fn ($q) => $q->where('pos_session_id', $session->id))
            ->get();

        $buckets = [];
        foreach ($methods as $code => $method) {
            $buckets[$code] = [
                'code' => $code,
                'name' => $method->name,
                'amount' => '0.00',
                'count' => 0,
                'is_cash' => $this->isCashHandler($method),
            ];
        }

        $cashSales = '0.00';
        $totalSales = '0.00';

        foreach ($payments as $payment) {
            $code = $this->resolvePaymentCode($payment, $methods);
            if (! isset($buckets[$code])) {
                $buckets[$code] = [
                    'code' => $code,
                    'name' => $code,
                    'amount' => '0.00',
                    'count' => 0,
                    'is_cash' => false,
                ];
            }

            $amount = number_format((float) $payment->amount, 2, '.', '');
            $buckets[$code]['amount'] = bcadd($buckets[$code]['amount'], $amount, 2);
            $buckets[$code]['count']++;
            $totalSales = bcadd($totalSales, $amount, 2);

            if ($buckets[$code]['is_cash']) {
                $cashSales = bcadd($cashSales, $amount, 2);
            }
        }

        $cashRefunds = $this->sumCashRefunds($session, $methods);
        $txnCount = $payments->count();
        $average = $txnCount > 0
            ? bcdiv($totalSales, (string) $txnCount, 2)
            : '0.00';

        return [
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'total_sales' => $totalSales,
            'transaction_count' => $txnCount,
            'average_sale' => $average,
            'payment_breakdown' => array_values($buckets),
        ];
    }

    /**
     * @param  Collection<string, PaymentMethodDefinition>  $methods
     */
    public function isCashHandler(PaymentMethodDefinition $method): bool
    {
        $handler = PosPaymentHandler::tryFrom((string) (($method->config ?? [])['handler'] ?? ''));

        return $handler === PosPaymentHandler::CashWithChange;
    }

    /**
     * Persist live running totals onto an open session after sale/refund.
     * Reconciles from Order / Payment / Refund engines — does not invent figures.
     */
    public function persistRunningTotals(PosSession $session): PosSession
    {
        if (! $session->isOpen()) {
            return $session;
        }

        $summary = $this->summarize($session);

        $session->forceFill([
            'cash_sales' => $summary['cash_sales'],
            'cash_refunds' => $summary['cash_refunds'],
            'expected_cash' => $summary['expected_cash'],
            'transaction_count' => $summary['transaction_count'],
            'payment_breakdown' => $summary['payment_breakdown'],
        ])->save();

        return $session->fresh() ?? $session;
    }

    /**
     * Snapshot totals onto the session row (used at close).
     *
     * @return array{expected_cash: string, cash_sales: string, cash_refunds: string, payment_breakdown: list<array<string, mixed>>, transaction_count: int}
     */
    public function snapshotForClose(PosSession $session): array
    {
        $summary = $this->summarize($session);

        return [
            'expected_cash' => $summary['expected_cash'],
            'cash_sales' => $summary['cash_sales'],
            'cash_refunds' => $summary['cash_refunds'],
            'payment_breakdown' => $summary['payment_breakdown'],
            'transaction_count' => $summary['transaction_count'],
        ];
    }

    /**
     * @return Collection<string, PaymentMethodDefinition>
     */
    private function posMethodsByCode(): Collection
    {
        return PaymentMethodDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (PaymentMethodDefinition $m) => ($m->config['pos_enabled'] ?? true) === true)
            ->keyBy(fn (PaymentMethodDefinition $m) => strtoupper($m->code));
    }

    /**
     * @param  Collection<string, PaymentMethodDefinition>  $methods
     */
    private function resolvePaymentCode(Payment $payment, Collection $methods): string
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $code = strtoupper((string) ($meta['payment_method_code'] ?? $payment->reference ?? ''));

        if ($code !== '' && $methods->has($code)) {
            return $code;
        }

        if ($code !== '') {
            return $code;
        }

        return 'UNKNOWN';
    }

    /**
     * Cash refunds for POS session orders (future-ready; currently sums completed cash-like refunds).
     *
     * @param  Collection<string, PaymentMethodDefinition>  $methods
     */
    private function sumCashRefunds(PosSession $session, Collection $methods): string
    {
        $cashCodes = $methods
            ->filter(fn (PaymentMethodDefinition $m) => $this->isCashHandler($m))
            ->keys()
            ->map(fn ($c) => strtoupper((string) $c))
            ->all();

        $refunds = RefundTransaction::query()
            ->where('status', RefundTransactionStatus::Completed)
            ->whereHas('order', fn ($q) => $q->where('pos_session_id', $session->id))
            ->get();

        $total = '0.00';
        foreach ($refunds as $refund) {
            $method = strtoupper((string) $refund->method);
            // Treat explicit cash codes or legacy "cash" as cash drawer impact.
            if (in_array($method, $cashCodes, true) || $method === 'CASH') {
                $total = bcadd($total, number_format((float) $refund->amount, 2, '.', ''), 2);
            }
        }

        return $total;
    }
}
