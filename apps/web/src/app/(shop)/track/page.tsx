import type { Metadata } from "next";
import { TrackOrderLookupContent } from "@/components/order/TrackOrderLookupContent";

export const metadata: Metadata = {
  title: "Track Order — CHINA ORDER TZ",
  description: "Enter your order ID to track delivery status and see real-time shipping updates.",
};

export default function TrackOrderLookupPage() {
  return <TrackOrderLookupContent lookupPath="/track" />;
}
