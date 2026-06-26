import type { Metadata } from "next";
import { CheckoutSummaryContent } from "@/components/checkout/CheckoutSummaryContent";

export const metadata: Metadata = {
  title: "Checkout Summary — CHINA ORDER TZ",
  description: "Review your order items, shipping methods, and totals before checkout.",
};

export default function CheckoutSummaryPage() {
  return <CheckoutSummaryContent />;
}
