import type { Metadata } from "next";
import { NmbHostedCheckoutContent } from "@/components/payment/NmbHostedCheckoutContent";

export const metadata: Metadata = {
  title: "NMB Secure Checkout | CHINA ORDER TZ",
  description: "Complete your payment securely with NMB Hosted Checkout.",
};

type NmbHostedCheckoutPageProps = {
  params: Promise<{
    paymentId: string;
  }>;
};

export default async function NmbHostedCheckoutPage({ params }: NmbHostedCheckoutPageProps) {
  const { paymentId } = await params;

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-10">
      <NmbHostedCheckoutContent paymentId={paymentId} />
    </main>
  );
}
