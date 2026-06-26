"use client";

import type { CartTotals } from "@/lib/types/cart";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { formatPrice } from "@/lib/catalog/utils";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { PaymentStatusBadge } from "./PaymentStatusBadge";

interface OrderSummaryPaymentProps {
  totals: CartTotals;
  paymentStatus?: PaymentStatus;
  paymentMethod?: PaymentMethodCode;
  paymentReference?: string | null;
  showPaymentDetails?: boolean;
}

export function OrderSummaryPayment({
  totals,
  paymentStatus,
  paymentMethod,
  paymentReference,
  showPaymentDetails = true,
}: OrderSummaryPaymentProps) {
  return (
    <div className="space-y-4">
      <OrderSummaryTotals totals={totals} hideZeroDiscount />

      {showPaymentDetails && paymentStatus && (
        <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
          <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Payment
          </p>

          <div className="mt-3 space-y-2.5">
            <div className="flex items-center justify-between gap-3">
              <span className="text-sm text-zinc-600">Status</span>
              <PaymentStatusBadge status={paymentStatus} size="sm" />
            </div>

            {paymentMethod && (
              <div className="flex items-center justify-between gap-3 text-sm">
                <span className="text-zinc-600">Method</span>
                <span className="font-semibold text-zinc-900">
                  {PAYMENT_METHOD_LABELS[paymentMethod] ?? paymentMethod}
                </span>
              </div>
            )}

            {paymentReference && (
              <div className="flex items-center justify-between gap-3 text-sm">
                <span className="text-zinc-600">Reference</span>
                <span className="font-mono text-xs font-semibold text-zinc-800">
                  {paymentReference}
                </span>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="relative overflow-hidden rounded-2xl border border-[#c9a227]/40 bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-800 p-[1px] shadow-[0_8px_32px_rgba(201,162,39,0.15)]">
        <div className="relative rounded-[15px] bg-gradient-to-br from-zinc-950 to-zinc-900 px-5 py-5">
          <div
            className="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-[#c9a227]/10 blur-2xl"
            aria-hidden
          />
          <div className="flex items-end justify-between gap-3">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#e8c547]">
                Grand Total
              </p>
              <p className="mt-1 text-xs text-zinc-400">All inclusive</p>
            </div>
            <p className="text-2xl font-bold tracking-tight text-[#e8c547] sm:text-3xl">
              {formatPrice(totals.grandTotal)}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
