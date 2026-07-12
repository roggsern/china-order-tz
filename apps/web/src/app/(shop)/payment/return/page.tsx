import type { Metadata } from "next";
import { Suspense } from "react";
import { NmbPaymentReturnContent } from "@/components/payment/NmbPaymentReturnContent";

export const metadata: Metadata = {
  title: "Payment Return | CHINA ORDER TZ",
  description: "Returning from NMB Hosted Checkout.",
};

export default function NmbPaymentReturnPage() {
  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-10">
      <Suspense
        fallback={
          <div className="mx-auto max-w-lg rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <p className="text-sm text-zinc-600">Processing your payment return…</p>
          </div>
        }
      >
        <NmbPaymentReturnContent />
      </Suspense>
    </main>
  );
}
