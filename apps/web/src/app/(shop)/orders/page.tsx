import type { Metadata } from "next";
import { MyOrdersContent } from "@/components/order/MyOrdersContent";

export const metadata: Metadata = {
  title: "My Orders — CHINA ORDER TZ",
  description: "View your order history, payment status, and shipping updates.",
};

export default function MyOrdersPage() {
  return <MyOrdersContent />;
}
