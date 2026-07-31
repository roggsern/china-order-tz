import type { Metadata } from "next";
import { PaymentPageContent } from "@/components/checkout/PaymentPageContent";

export const metadata: Metadata = {
  title: "Payment — CHINA ORDER TZ",
  description: "Complete your order with secure NMB checkout.",
};

export default function CheckoutPaymentPage() {
  return <PaymentPageContent />;
}
