import { PaymentOrchestratorPage } from "@/components/payment/PaymentOrchestratorPage";

type PageProps = {
  params: Promise<{ transactionId: string }>;
};

export default async function PaymentTransactionPage({ params }: PageProps) {
  const { transactionId } = await params;

  return <PaymentOrchestratorPage transactionId={transactionId} />;
}
