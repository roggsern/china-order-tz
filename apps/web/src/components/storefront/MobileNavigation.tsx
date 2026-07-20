"use client";

import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useId, useRef } from "react";
import { ActiveOrderBadge } from "@/components/storefront/ActiveOrderBadge";
import { BuyFromTzMegaMenu } from "@/components/home/BuyFromTzMegaMenu";
import { CloseIcon } from "@/components/home/icons";
import { MegaMenu } from "@/components/home/MegaMenu";
import { logoutCustomer } from "@/lib/customer/logout-customer";
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

const rowClass = `flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 transition-colors duration-200 ease-out active:bg-zinc-100 ${storefrontTypography.navMobile}`;

type MobileNavigationProps = {
  open: boolean;
  drawerActive: boolean;
  onClose: () => void;
  audience: StorefrontNavAudience;
};

export function MobileNavigation({
  open,
  drawerActive,
  onClose,
  audience,
}: MobileNavigationProps) {
  const router = useRouter();
  const pathname = usePathname() || "/";
  const searchParams = useSearchParams();
  const search = searchParams?.toString() ? `?${searchParams.toString()}` : "";
  const activeJourney = resolveActiveJourney(pathname, search);
  const { navigation } = useStorefrontNavigation(audience);
  const { count: activeOrderCount, show: showOrderBadge } = useActiveOrdersBadge();
  const titleId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    previouslyFocused.current = document.activeElement as HTMLElement | null;
    const frame = requestAnimationFrame(() => closeButtonRef.current?.focus());

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    document.addEventListener("keydown", onKeyDown);

    return () => {
      cancelAnimationFrame(frame);
      document.removeEventListener("keydown", onKeyDown);
      previouslyFocused.current?.focus?.();
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  const handleSignOut = () => {
    onClose();
    logoutCustomer();
    router.push("/");
  };

  const items = navigation.mobile;
  const journeyItems = items.filter(
    (item) => item.kind === "china_mega" || item.kind === "tz_mega",
  );
  const restItems = items.filter(
    (item) => item.kind !== "china_mega" && item.kind !== "tz_mega",
  );

  const renderRestItem = (item: ResolvedNavItem) => {
    if (item.kind === "group") {
      return (
        <li key={item.key}>
          <p className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">
            {item.label}
          </p>
          <ul>
            {(item.children ?? []).map((child) => renderRestItem(child))}
          </ul>
        </li>
      );
    }

    const policyId = (item.policyId ?? null) as StorefrontNavItemId | null;
    if (policyId === "signOut") {
      return (
        <li key={item.key}>
          <button
            type="button"
            onClick={handleSignOut}
            className={`${rowClass} w-full text-left text-red-600`}
          >
            {item.label}
          </button>
        </li>
      );
    }

    const active = policyId ? isNavItemActive(policyId, activeJourney) : false;
    const isOrders = policyId === "myOrders";

    return (
      <li key={item.key}>
        <Link
          href={item.href || "#"}
          onClick={onClose}
          className={`${rowClass} ${active ? "text-zinc-900" : ""}`}
          aria-current={active ? "page" : undefined}
        >
          <span className="inline-flex items-center gap-2">
            {item.label}
            {isOrders && showOrderBadge ? (
              <ActiveOrderBadge count={activeOrderCount} />
            ) : null}
          </span>
        </Link>
      </li>
    );
  };

  return (
    <div className="fixed inset-0 z-[60] md:hidden" role="presentation">
      <button
        type="button"
        aria-label="Close menu"
        onClick={onClose}
        className={`absolute inset-0 bg-black/50 backdrop-blur-[2px] transition-opacity duration-300 ease-out ${
          drawerActive ? "opacity-100" : "opacity-0"
        }`}
      />

      <nav
        aria-labelledby={titleId}
        aria-modal="true"
        role="dialog"
        className={`fixed inset-y-0 left-0 z-10 flex w-[90vw] max-w-[90vw] flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.12)] transition-transform duration-300 ease-out ${
          drawerActive ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="flex shrink-0 items-center justify-between border-b border-zinc-100 px-4 py-4">
          <span id={titleId} className="text-base font-semibold tracking-tight text-zinc-900">
            Menu
          </span>
          <button
            ref={closeButtonRef}
            type="button"
            onClick={onClose}
            className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
            aria-label="Close menu"
          >
            <CloseIcon className="h-5 w-5" />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3">
          <ul className="divide-y divide-zinc-100/90">
            {journeyItems.map((item) => (
              <li key={item.key} className="py-1.5">
                {item.kind === "china_mega" ? (
                  <MegaMenu
                    mobile
                    onNavigate={onClose}
                    triggerLabel={item.label}
                    showCountryFlag
                  />
                ) : (
                  <BuyFromTzMegaMenu
                    mobile
                    onNavigate={onClose}
                    triggerLabel={item.label}
                    showCountryFlag
                  />
                )}
              </li>
            ))}
          </ul>

          <ul className="mt-1 border-t border-zinc-100 pt-1">
            {restItems.map((item) => renderRestItem(item))}
          </ul>
        </div>
      </nav>
    </div>
  );
}
