import { NmbPaymentTransactionHostedCheckoutContent } from "@/components/payment/NmbPaymentTransactionHostedCheckoutContent";

type PageProps = {
  params: Promise<{ transactionId: string }>;
  searchParams: Promise<{
    sessionId?: string;
    successIndicator?: string;
  }>;
};

export default async function NmbPaymentTransactionCheckoutPage({
  params,
  searchParams,
}: PageProps) {
  const { transactionId } = await params;
  const query = await searchParams;

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-10">
      <NmbPaymentTransactionHostedCheckoutContent
        paymentTransactionId={transactionId}
        sessionId={query.sessionId}
        successIndicator={query.successIndicator ?? null}
      />
    </main>
  );
}
