"use client";

import { Suspense, useEffect, useState } from "react";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { CartIconButton } from "@/components/cart/CartIconButton";
import { NotificationBellButton } from "@/components/notifications/NotificationBellButton";
import { AccountNavigation } from "@/components/storefront/AccountNavigation";
import { DesktopNavigation } from "@/components/storefront/DesktopNavigation";
import { MobileNavigation } from "@/components/storefront/MobileNavigation";
import { CloseIcon, MenuIcon, SearchIcon } from "./icons";
import { TrendingSearches } from "@/components/home/commercial/TrendingSearches";
import { SearchExperience } from "@/components/search/SearchExperience";
import { SearchOverlay } from "@/components/search/SearchOverlay";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { homepageContentSeed } from "@/lib/content/homepage";
import {
  resolveStorefrontNavAudience,
  shouldShowNotifications,
} from "@/lib/storefront/navigation-policy";
import { storefrontTypography } from "@/lib/storefront/typography";

const mobileHeaderIconClass =
  "inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg p-2 text-zinc-600 transition-all duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900 active:bg-zinc-100";

const desktopUtilityClass = `inline-flex h-[52px] shrink-0 items-center gap-2 whitespace-nowrap transition-colors duration-200 ease-out hover:text-zinc-900 ${storefrontTypography.navSecondary}`;

export function Header() {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false);
  const [drawerActive, setDrawerActive] = useState(false);
  const { isLoggedIn, isReady } = useCustomerSession();
  const audience = resolveStorefrontNavAudience({ isLoggedIn: isReady && isLoggedIn });
  const showNotifications = shouldShowNotifications(audience);

  useEffect(() => {
    if (mobileOpen) {
      document.body.style.overflow = "hidden";
      const frame = requestAnimationFrame(() => setDrawerActive(true));
      return () => cancelAnimationFrame(frame);
    }

    setDrawerActive(false);
    document.body.style.overflow = "";
  }, [mobileOpen]);

  const closeMobile = () => {
    setDrawerActive(false);
    window.setTimeout(() => setMobileOpen(false), 300);
  };

  const openMobile = () => setMobileOpen(true);

  return (
    <>
      <header className="sticky top-0 z-50 border-b border-zinc-200/60 bg-white/95 shadow-[0_4px_24px_rgba(0,0,0,0.03)] backdrop-blur-md">
        {/* Desktop & tablet */}
        <div className="hidden md:block">
          <div className="border-b border-zinc-100/90">
            <div className="mx-auto flex min-h-[88px] max-w-[1440px] items-center gap-5 px-6 py-3 lg:gap-6 lg:px-8 xl:px-10">
              <HorizontalBrandLogo className="shrink-0" size="header" height={64} />

              <div className="flex min-w-0 flex-1 flex-col items-center justify-center gap-1.5">
                <Suspense
                  fallback={
                    <div className="h-[52px] w-full max-w-[700px] animate-pulse rounded-xl bg-zinc-100" />
                  }
                >
                  <SearchExperience size="large" className="w-full max-w-[700px]" />
                </Suspense>
                <TrendingSearches
                  terms={homepageContentSeed.trendingSearches}
                  className="hidden w-full max-w-[700px] px-1 lg:flex"
                />
              </div>

              <div className="flex shrink-0 items-center gap-4 pl-6 lg:gap-6 lg:pl-8">
                {showNotifications ? (
                  <NotificationBellButton
                    showLabel
                    className={desktopUtilityClass}
                    labelClassName="hidden lg:inline"
                  />
                ) : null}

                <AccountNavigation
                  audience={audience}
                  variant="desktop"
                  className={desktopUtilityClass}
                  iconClassName="h-[18px] w-[18px] shrink-0"
                  labelClassName="hidden lg:inline"
                />

                <CartIconButton
                  showLabel
                  className={desktopUtilityClass}
                  labelClassName="hidden lg:inline"
                />
              </div>
            </div>
          </div>

          <Suspense fallback={<div className="h-[52px] border-b border-zinc-100 bg-white" />}>
            <DesktopNavigation audience={audience} />
          </Suspense>
        </div>

        {/* Mobile */}
        <div className="md:hidden">
          <div className="border-b border-zinc-100 px-4 sm:px-6">
            <div className="flex h-16 items-center gap-4">
              <HorizontalBrandLogo size="header" height={52} />

              <div className="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1">
                <button
                  type="button"
                  onClick={() => setMobileSearchOpen(true)}
                  className={mobileHeaderIconClass}
                  aria-label="Search products"
                >
                  <SearchIcon className="h-5 w-5" />
                </button>

                {showNotifications ? (
                  <NotificationBellButton
                    className={`${mobileHeaderIconClass} relative`}
                    iconClassName="h-5 w-5"
                    badgeClassName="absolute right-0 top-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#c9a227] px-0.5 text-[9px] font-bold text-zinc-900"
                  />
                ) : null}

                <CartIconButton
                  className={`${mobileHeaderIconClass} relative`}
                  iconClassName="h-5 w-5"
                  badgeClassName="absolute right-0 top-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#c9a227] px-0.5 text-[9px] font-bold text-zinc-900"
                />

                <button
                  type="button"
                  onClick={mobileOpen ? closeMobile : openMobile}
                  className={mobileHeaderIconClass}
                  aria-expanded={mobileOpen}
                  aria-label={mobileOpen ? "Close menu" : "Open menu"}
                >
                  {mobileOpen ? (
                    <CloseIcon className="h-5 w-5" />
                  ) : (
                    <MenuIcon className="h-5 w-5" />
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <Suspense fallback={null}>
        <SearchOverlay open={mobileSearchOpen} onClose={() => setMobileSearchOpen(false)} />
      </Suspense>

      <Suspense fallback={null}>
        <MobileNavigation
          open={mobileOpen}
          drawerActive={drawerActive}
          onClose={closeMobile}
          audience={audience}
        />
      </Suspense>
    </>
  );
}
