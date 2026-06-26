import type { Metadata } from "next";
import { TrackOrderContent } from "@/components/order/TrackOrderContent";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export const metadata: Metadata = {
  title: "Track Order — CHINA ORDER TZ",
  description: "Track your CHINA ORDER TZ order status and delivery progress.",
};

export default async function TrackOrderPage({ params }: PageProps) {
  const { orderId } = await params;
  return <TrackOrderContent orderId={orderId} />;
}
