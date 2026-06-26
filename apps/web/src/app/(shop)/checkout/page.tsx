import type { Metadata } from "next";
import { CheckoutPageContent } from "@/components/checkout";

export const metadata: Metadata = {
  title: "Checkout — CHINA ORDER TZ",
  description:
    "Complete your order with customer details, shipping address, and transparent pricing in Tanzanian Shillings.",
};

export default function CheckoutPage() {
  return <CheckoutPageContent />;
}
