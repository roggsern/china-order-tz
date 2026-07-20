import type { Metadata } from "next";
import { RetailIntelligenceDashboard } from "@/components/admin/analytics/RetailIntelligenceDashboard";

export const metadata: Metadata = {
  title: "Retail Analytics — Admin — CHINA ORDER TZ",
  description: "Enterprise retail intelligence — sales, profit, inventory, returns, sessions.",
};

export default function AdminAnalyticsPage() {
  return <RetailIntelligenceDashboard />;
}
