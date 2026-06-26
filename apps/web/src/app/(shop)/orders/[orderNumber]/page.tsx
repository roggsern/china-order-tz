import type { Metadata } from "next";
import { OrderDetailsContent } from "@/components/order/OrderDetailsContent";

type PageProps = {
  params: Promise<{ orderNumber: string }>;
};

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { orderNumber } = await params;
  return {
    title: `Order ${orderNumber} — CHINA ORDER TZ`,
    description: "View your order details, shipping, and payment status.",
  };
}

export default async function OrderDetailsPage({ params }: PageProps) {
  const { orderNumber } = await params;
  return <OrderDetailsContent orderNumber={orderNumber} />;
}
