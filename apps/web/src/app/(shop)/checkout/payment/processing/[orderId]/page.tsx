import type { Metadata } from "next";
import { Suspense } from "react";
import { PaymentProcessingContent } from "@/components/checkout/PaymentProcessingContent";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export const metadata: Metadata = {
  title: "Processing Payment — CHINA ORDER TZ",
  description: "Waiting for M-Pesa STK Push confirmation.",
};

export default async function PaymentProcessingPage({ params }: PageProps) {
  const { orderId } = await params;

  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6" aria-busy="true">
          <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
        </div>
      }
    >
      <PaymentProcessingContent orderId={orderId} />
    </Suspense>
  );
}
