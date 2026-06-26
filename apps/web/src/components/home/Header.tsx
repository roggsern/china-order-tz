"use client";

import { useState } from "react";
import Link from "next/link";
import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";
import { useCart } from "@/lib/cart/context";
import { headerSecondaryNav } from "@/lib/home-data";
import { BrandMegaMenu } from "./BrandMegaMenu";
import { CartIcon, CloseIcon, MenuIcon, UserIcon } from "./icons";
import { MegaMenu } from "./MegaMenu";
import { SearchBar } from "./SearchBar";

const navLinkClass =
  "inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-3.5 py-2 text-[13px] font-medium tracking-wide text-zinc-600 transition-colors hover:bg-zinc-50 hover:text-zinc-900 xl:px-4 xl:text-sm";

const allCategoriesClass =
  "inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3.5 py-2 text-[13px] font-semibold tracking-wide text-zinc-900 transition-colors hover:bg-zinc-50 xl:px-4 xl:text-sm";

const orderFromChinaButtonClass =
  "inline-flex h-[52px] shrink-0 items-center whitespace-nowrap rounded-xl bg-[#c9a227] px-6 text-[11px] font-bold uppercase tracking-[0.1em] text-zinc-900 shadow-[0_2px_12px_rgba(201,162,39,0.35)] transition hover:bg-[#e8c547] hover:shadow-[0_4px_16px_rgba(201,162,39,0.45)]";

export function Header() {
  const [mobileOpen, setMobileOpen] = useState(false);
  const { totals, isHydrated } = useCart();
  const cartCount = isHydrated ? totals.itemCount : 0;

  const closeMobile = () => setMobileOpen(false);

  return (
    <header className="sticky top-0 z-50 border-b border-zinc-100/80 bg-white/95 shadow-[0_4px_24px_rgba(0,0,0,0.04)] backdrop-blur-md">
      {/* Desktop & tablet */}
      <div className="hidden md:block">
        <div className="border-b border-zinc-100">
          <div className="mx-auto grid min-h-[88px] max-w-[1440px] grid-cols-[1fr_auto_1fr] items-center gap-6 px-6 lg:gap-8 lg:px-8 xl:px-10">
            <HorizontalBrandLogo className="justify-self-start" size="header" height={64} />

            <SearchBar
              size="large"
              className="w-full max-w-[700px] justify-self-center lg:w-[700px]"
            />

            <div className="flex shrink-0 items-center justify-self-end gap-6 lg:gap-8">
              <Link
                href="/login"
                className="inline-flex h-[52px] shrink-0 items-center gap-2 whitespace-nowrap text-[13px] font-medium text-zinc-500 transition-colors hover:text-zinc-900 xl:text-sm"
              >
                <UserIcon className="h-[18px] w-[18px] shrink-0" />
                <span className="hidden lg:inline">Login</span>
              </Link>

              <Link
                href="/cart"
                className="inline-flex h-[52px] shrink-0 items-center gap-2 whitespace-nowrap text-[13px] font-medium text-zinc-600 transition-colors hover:text-zinc-900 xl:text-sm"
              >
                <span className="relative shrink-0">
                  <CartIcon className="h-[18px] w-[18px]" />
                  {cartCount > 0 && (
                    <span className="absolute -right-2.5 -top-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#c9a227] px-1 text-[10px] font-bold text-zinc-900">
                      {cartCount > 99 ? "99+" : cartCount}
                    </span>
                  )}
                </span>
                <span className="hidden lg:inline">Cart</span>
              </Link>

              <Link href="#order-from-china" className={orderFromChinaButtonClass}>
                Order From China
              </Link>
            </div>
          </div>
        </div>

        <nav className="border-b border-zinc-100/80" aria-label="Main navigation">
          <div className="mx-auto flex h-12 max-w-[1440px] items-center gap-0.5 px-6 lg:gap-1 lg:px-8 xl:px-10">
            <MegaMenu
              triggerLabel="All Categories"
              align="left"
              linkClassName={allCategoriesClass}
            />

            <BrandMegaMenu triggerLabel="Buy From TZ" align="left" linkClassName={navLinkClass} />

            {headerSecondaryNav.map((item) => (
              <Link key={item.href} href={item.href} className={navLinkClass}>
                {item.label}
              </Link>
            ))}
          </div>
        </nav>
      </div>

      {/* Mobile */}
      <div className="md:hidden">
        <div className="border-b border-zinc-100 px-4 sm:px-6">
          <div className="flex h-16 items-center gap-4">
            <HorizontalBrandLogo size="header" height={52} />

            <div className="ml-auto flex shrink-0 items-center gap-1">
              <Link
                href="/cart"
                className="relative inline-flex shrink-0 items-center rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900"
                aria-label="Cart"
              >
                <CartIcon className="h-5 w-5" />
                {cartCount > 0 && (
                  <span className="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-[#c9a227] text-[9px] font-bold text-zinc-900">
                    {cartCount > 99 ? "99+" : cartCount}
                  </span>
                )}
              </Link>

              <button
                type="button"
                onClick={() => setMobileOpen((open) => !open)}
                className="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-50"
                aria-expanded={mobileOpen}
                aria-label={mobileOpen ? "Close menu" : "Open menu"}
              >
                {mobileOpen ? <CloseIcon className="h-5 w-5" /> : <MenuIcon className="h-5 w-5" />}
              </button>
            </div>
          </div>

          <div className="pb-4">
            <SearchBar placeholder="Search products, brands or categories..." />
          </div>
        </div>

        {mobileOpen && (
          <nav className="border-b border-zinc-100 bg-white px-4 py-4 sm:px-6">
            <ul className="space-y-1">
              <li>
                <MegaMenu
                  mobile
                  onNavigate={closeMobile}
                  triggerLabel="All Categories"
                  showHamburger
                />
              </li>
              <li>
                <BrandMegaMenu mobile onNavigate={closeMobile} triggerLabel="Buy From TZ" />
              </li>
              {headerSecondaryNav.map((item) => (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    onClick={closeMobile}
                    className="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
                  >
                    {item.label}
                  </Link>
                </li>
              ))}
              <li className="pt-2">
                <Link
                  href="/login"
                  onClick={closeMobile}
                  className="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
                >
                  <UserIcon className="h-4 w-4" />
                  Login
                </Link>
              </li>
              <li>
                <Link
                  href="#order-from-china"
                  onClick={closeMobile}
                  className={`${orderFromChinaButtonClass} w-full justify-center px-3 py-3`}
                >
                  Order From China
                </Link>
              </li>
            </ul>
          </nav>
        )}
      </div>
    </header>
  );
}
