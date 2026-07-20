import type { Metadata } from "next";
import { CustomerReturnRequestContent } from "@/components/order/CustomerReturnRequestContent";

type PageProps = {
  params: Promise<{ orderNumber: string }>;
};

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { orderNumber } = await params;
  return {
    title: `Return ${orderNumber} — CHINA ORDER TZ`,
    description: "Request a return for items from this order.",
  };
}

export default async function OrderReturnPage({ params }: PageProps) {
  const { orderNumber } = await params;
  return <CustomerReturnRequestContent orderNumber={orderNumber} />;
}
