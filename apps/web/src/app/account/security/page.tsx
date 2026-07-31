"use client";

import { Suspense } from "react";
import { AccountSecurityContent } from "@/components/account/AccountSecurityContent";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";

export default function AccountSecurityPage() {
  return (
    <StorefrontShell>
      <Suspense
        fallback={
          <div className="mx-auto max-w-xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <AccountPageSkeleton />
          </div>
        }
      >
        <AccountSecurityContent />
      </Suspense>
    </StorefrontShell>
  );
}
