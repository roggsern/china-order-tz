import type { Metadata } from "next";
import { CheckoutSuccessRedirect } from "@/components/order/CheckoutSuccessRedirect";

type PageProps = {
  params: Promise<{ orderNumber: string }>;
};

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { orderNumber } = await params;
  return {
    title: `Order ${orderNumber} — CHINA ORDER TZ`,
    description: "Your order confirmation from CHINA ORDER TZ.",
  };
}

export default async function CheckoutSuccessPage({ params }: PageProps) {
  const { orderNumber } = await params;
  return <CheckoutSuccessRedirect orderNumber={orderNumber} />;
}
