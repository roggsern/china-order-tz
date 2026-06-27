import type { Metadata } from "next";
import { Suspense } from "react";
import { PaymentConfirmationContent } from "@/components/checkout/PaymentConfirmationContent";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export const metadata: Metadata = {
  title: "Payment Confirmation — CHINA ORDER TZ",
  description: "Confirm your M-Pesa payment and view order status.",
};

export default async function PaymentConfirmationPage({ params }: PageProps) {
  const { orderId } = await params;

  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6" aria-busy="true">
          <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
        </div>
      }
    >
      <PaymentConfirmationContent orderId={orderId} />
    </Suspense>
  );
}
