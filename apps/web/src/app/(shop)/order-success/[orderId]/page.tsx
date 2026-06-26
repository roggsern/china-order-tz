import type { Metadata } from "next";
import { OrderSuccessContent } from "@/components/order/OrderSuccessContent";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { orderId } = await params;
  return {
    title: `Order Confirmation — CHINA ORDER TZ`,
    description: "Your order confirmation from CHINA ORDER TZ.",
  };
}

export default async function OrderSuccessPage({ params }: PageProps) {
  const { orderId } = await params;
  return <OrderSuccessContent orderId={orderId} />;
}
