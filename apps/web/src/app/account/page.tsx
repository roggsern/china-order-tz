"use client";

import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountDashboardContent } from "@/components/account/AccountDashboardContent";

export default function AccountPage() {
  return (
    <StorefrontShell>
      <AccountDashboardContent />
    </StorefrontShell>
  );
}
