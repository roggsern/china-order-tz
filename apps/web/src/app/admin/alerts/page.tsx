import type { Metadata } from "next";
import { AdminAlertsPanel } from "@/components/admin/AdminAlertsPanel";

export const metadata: Metadata = {
  title: "Alerts — Admin — CHINA ORDER TZ",
  description: "Operational and growth alerts for the admin command center.",
};

export default function AdminAlertsPage() {
  return <AdminAlertsPanel />;
}
