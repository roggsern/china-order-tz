import type { Metadata } from "next";
import { AdminAnalyticsDashboard } from "@/components/admin/AdminAnalyticsDashboard";

export const metadata: Metadata = {
  title: "Analytics — Admin — CHINA ORDER TZ",
  description: "Real-time ecommerce analytics and business intelligence.",
};

export default function AdminAnalyticsPage() {
  return <AdminAnalyticsDashboard />;
}
