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
        <div className="min-h-[70vh] bg-zinc-950 px-4 py-16 sm:px-6" aria-busy="true">
          <div className="mx-auto h-24 w-24 animate-pulse rounded-full bg-zinc-800" />
        </div>
      }
    >
      <PaymentProcessingContent orderId={orderId} />
    </Suspense>
  );
}
