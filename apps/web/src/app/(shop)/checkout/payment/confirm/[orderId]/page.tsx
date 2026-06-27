import { redirect } from "next/navigation";

type PageProps = {
  params: Promise<{ orderId: string }>;
  searchParams: Promise<{ transactionId?: string; simulated?: string }>;
};

/** Legacy route — forwards to the STK processing experience. */
export default async function PaymentConfirmRedirectPage({
  params,
  searchParams,
}: PageProps) {
  const { orderId } = await params;
  const query = await searchParams;

  const urlParams = new URLSearchParams();
  if (query.transactionId) {
    urlParams.set("transactionId", query.transactionId);
  }
  if (query.simulated) {
    urlParams.set("simulated", query.simulated);
  }

  const suffix = urlParams.toString();
  redirect(`/checkout/payment/processing/${orderId}${suffix ? `?${suffix}` : ""}`);
}
