import type { Metadata } from "next";
import { CartPageContent } from "@/components/cart";

export const metadata: Metadata = {
  title: "Shopping Cart — CHINA ORDER TZ",
  description: "Review items in your shopping cart before checkout.",
};

export default function CartPage() {
  return <CartPageContent />;
}
