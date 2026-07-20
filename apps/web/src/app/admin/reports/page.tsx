import type { Metadata } from "next";
import { AdminReportsPanel } from "@/components/admin/AdminReportsPanel";

export const metadata: Metadata = {
  title: "Reports — Admin — CHINA ORDER TZ",
  description: "Admin reporting platform — sales, orders, payments, warehouse, and more.",
};

export default function AdminReportsPage() {
  return <AdminReportsPanel />;
}
