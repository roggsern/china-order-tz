"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { ActiveOrderBadge } from "@/components/storefront/ActiveOrderBadge";
import { BuyFromTzMegaMenu } from "@/components/home/BuyFromTzMegaMenu";
import { MegaMenu } from "@/components/home/MegaMenu";
import {
  isNavItemActive,
  resolveActiveJourney,
  type StorefrontNavAudience,
  type StorefrontNavItemId,
} from "@/lib/storefront/navigation-policy";
import { storefrontTypography } from "@/lib/storefront/typography";
import { useActiveOrdersBadge } from "@/lib/storefront/use-active-orders-badge";
import { useStorefrontNavigation } from "@/lib/storefront/use-storefront-navigation";
import type { ResolvedNavItem } from "@/lib/storefront/resolve-storefront-navigation";

const journeyTriggerClass =
  `group inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2.5 transition-colors duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900 xl:px-4 ${storefrontTypography.navPrimary}`;

const plainNavClass =
  `inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-lg px-3 py-2.5 transition-colors duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900 xl:px-4 ${storefrontTypography.navPrimary}`;

const activeClass =
  "relative text-zinc-900 after:absolute after:inset-x-3 after:bottom-1 after:h-[2px] after:rounded-full after:bg-[#c9a227]";

type DesktopNavigationProps = {
  audience: StorefrontNavAudience;
};

function renderPrimaryItem(
  item: ResolvedNavItem,
  activeJourney: ReturnType<typeof resolveActiveJourney>,
  showOrderBadge: boolean,
  activeOrderCount: number,
) {
  if (item.kind === "china_mega") {
    return (
      <MegaMenu
        key={item.key}
        triggerLabel={item.label}
        align="left"
        showCountryFlag
        linkClassName={`${journeyTriggerClass} ${
          isNavItemActive("orderFromChina", activeJourney) ? activeClass : ""
        }`}
      />
    );
  }

  if (item.kind === "tz_mega") {
    return (
      <BuyFromTzMegaMenu
        key={item.key}
        triggerLabel={item.label}
        align="left"
        showCountryFlag
        linkClassName={`${journeyTriggerClass} ${
          isNavItemActive("buyFromTz", activeJourney) ? activeClass : ""
        }`}
      />
    );
  }

  if (item.kind === "group") {
    return (
      <div key={item.key} className="flex items-center gap-0.5">
        {(item.children ?? []).map((child) =>
          renderPrimaryItem(child, activeJourney, showOrderBadge, activeOrderCount),
        )}
      </div>
    );
  }

  const policyId = (item.policyId ?? null) as StorefrontNavItemId | null;
  const active = policyId ? isNavItemActive(policyId, activeJourney) : false;
  const isOrders = policyId === "myOrders";

  return (
    <Link
      key={item.key}
      href={item.href || "#"}
      className={`${plainNavClass} ${active ? activeClass : ""}`}
      aria-current={active ? "page" : undefined}
    >
      <span className="inline-flex items-center gap-2">
        {item.label}
        {isOrders && showOrderBadge ? (
          <ActiveOrderBadge count={activeOrderCount} />
        ) : null}
      </span>
    </Link>
  );
}

export function DesktopNavigation({ audience }: DesktopNavigationProps) {
  const pathname = usePathname() || "/";
  const searchParams = useSearchParams();
  const search = searchParams?.toString() ? `?${searchParams.toString()}` : "";
  const activeJourney = resolveActiveJourney(pathname, search);
  const { navigation } = useStorefrontNavigation(audience);
  const { count: activeOrderCount, show: showOrderBadge } = useActiveOrdersBadge();

  const items = navigation.primary;
  const journeyItems = items.filter(
    (item) => item.kind === "china_mega" || item.kind === "tz_mega",
  );
  const restItems = items.filter(
    (item) => item.kind !== "china_mega" && item.kind !== "tz_mega",
  );

  return (
    <nav className="border-b border-zinc-100/80 bg-white" aria-label="Main navigation">
      <div className="mx-auto flex h-[52px] max-w-[1440px] items-center gap-0.5 px-6 lg:gap-1 lg:px-8 xl:px-10">
        {journeyItems.map((item, index) => (
          <div key={item.key} className="contents">
            {index > 0 ? (
              <div
                className="mx-2 hidden h-5 w-px bg-zinc-200 lg:mx-3 lg:block"
                aria-hidden="true"
              />
            ) : null}
            {renderPrimaryItem(item, activeJourney, showOrderBadge, activeOrderCount)}
          </div>
        ))}

        {restItems.map((item) =>
          renderPrimaryItem(item, activeJourney, showOrderBadge, activeOrderCount),
        )}
      </div>
    </nav>
  );
}
