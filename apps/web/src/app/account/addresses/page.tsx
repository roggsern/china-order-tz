"use client";

import Link from "next/link";
import { AccountComingSoonPanel } from "@/components/account/AccountComingSoonPanel";
import { AddressSummaryIcon } from "@/components/account/AccountIcons";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";

export default function AccountAddressesPage() {
  const { isReady } = useCustomerSession();

  return (
    <StorefrontShell>
      <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <nav aria-label="Breadcrumb" className="mb-6">
          <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <li>
              <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
                My Account
              </Link>
            </li>
            <li aria-hidden>/</li>
            <li className="font-semibold text-zinc-900">Saved Addresses</li>
          </ol>
        </nav>

        {!isReady ? (
          <AccountPageSkeleton />
        ) : (
          <AccountComingSoonPanel
            icon={<AddressSummaryIcon className="h-9 w-9" />}
            title="Saved Addresses"
            description="Saved addresses will help you checkout faster."
            footnote="Coming soon."
          />
        )}
      </div>
    </StorefrontShell>
  );
}
