import type { Metadata } from "next";
import { PaymentPageContent } from "@/components/checkout/PaymentPageContent";

export const metadata: Metadata = {
  title: "Payment — CHINA ORDER TZ",
  description:
    "Choose your payment method and complete your order with M-Pesa, cash on delivery, or bank transfer.",
};

export default function CheckoutPaymentPage() {
  return <PaymentPageContent />;
}
