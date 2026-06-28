import type { Metadata } from "next";
import { TrackOrderLiveContent } from "@/components/order/TrackOrderLiveContent";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export const metadata: Metadata = {
  title: "Track Order — CHINA ORDER TZ",
  description: "Real-time order tracking from purchase to delivery.",
};

export default async function TrackOrderDetailPage({ params }: PageProps) {
  const { orderId } = await params;
  return <TrackOrderLiveContent orderId={orderId} />;
}
