import type { Metadata } from "next";
import { CustomerReturnsListContent } from "@/components/order/CustomerReturnsListContent";

export const metadata: Metadata = {
  title: "My Returns — CHINA ORDER TZ",
  description: "View your return requests and refund status.",
};

export default function CustomerReturnsPage() {
  return <CustomerReturnsListContent />;
}
