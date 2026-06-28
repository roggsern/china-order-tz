"use client";

import { motion } from "framer-motion";
import { formatPrice } from "@/lib/catalog/utils";
import { CopyOrderNumber } from "@/components/order/CopyOrderNumber";

interface MpesaPaymentSuccessPanelProps {
  orderNumber: string;
  transactionId: string | null;
  paymentReference: string | null;
  amount: number;
}

export function MpesaPaymentSuccessPanel({
  orderNumber,
  transactionId,
  paymentReference,
  amount,
}: MpesaPaymentSuccessPanelProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.15, duration: 0.4 }}
      className="mt-8 overflow-hidden rounded-2xl border border-emerald-500/25 bg-gradient-to-b from-emerald-500/10 to-zinc-900/80 p-5 sm:p-6"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <CopyOrderNumber orderNumber={orderNumber} theme="dark" />
        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-400">
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden />
          Paid
        </span>
      </div>

      <dl className="mt-5 space-y-3 text-sm">
        <div className="flex items-center justify-between gap-4 border-b border-zinc-800 pb-3">
          <dt className="text-zinc-500">Amount paid</dt>
          <dd className="font-bold text-[#e8c547]">{formatPrice(amount)}</dd>
        </div>

        {transactionId ? (
          <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <dt className="text-zinc-500">Transaction ID</dt>
            <dd className="font-mono text-xs font-semibold text-zinc-200 sm:text-sm">{transactionId}</dd>
          </div>
        ) : null}

        {paymentReference ? (
          <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <dt className="text-zinc-500">M-Pesa receipt</dt>
            <dd className="font-mono text-xs font-semibold text-zinc-200 sm:text-sm">{paymentReference}</dd>
          </div>
        ) : null}
      </dl>

      <p className="mt-4 text-center text-xs text-zinc-500">
        Redirecting to order confirmation…
      </p>
    </motion.div>
  );
}
