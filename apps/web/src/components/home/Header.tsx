"use client";

import { useState } from "react";
import Link from "next/link";
import { navLinks } from "@/lib/home-data";
import { CartIcon, CloseIcon, MenuIcon, UserIcon } from "./icons";
import { MegaMenu } from "./MegaMenu";
import { SearchBar } from "./SearchBar";

export function Header() {
  const [mobileOpen, setMobileOpen] = useState(false);

  const closeMobile = () => setMobileOpen(false);

  return (
    <header className="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/90 backdrop-blur-xl">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex h-16 items-center gap-4 lg:h-[72px] lg:gap-6">
          <Link href="/" className="group flex shrink-0 items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#c9a227] to-[#8b6914] text-sm font-black text-white shadow-md shadow-[#c9a227]/30 transition group-hover:shadow-[#c9a227]/50">
              C
            </span>
            <div className="hidden leading-tight sm:block">
              <span className="block text-[10px] font-semibold uppercase tracking-[0.25em] text-zinc-400">
                China
              </span>
              <span className="block text-sm font-bold tracking-tight text-zinc-900">
                ORDER <span className="text-[#c9a227]">TZ</span>
              </span>
            </div>
          </Link>

          <div className="hidden flex-1 lg:block">
            <SearchBar className="max-w-xl" />
          </div>

          <nav className="hidden items-center gap-0.5 lg:flex">
            <MegaMenu />
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900"
              >
                {link.label}
              </Link>
            ))}
          </nav>

          <div className="ml-auto flex items-center gap-1 sm:gap-2">
            <Link
              href="#order-from-china"
              className="hidden rounded-full bg-[#c9a227]/10 px-3 py-2 text-xs font-bold uppercase tracking-wide text-[#8b6914] transition hover:bg-[#c9a227]/20 md:inline-flex"
            >
              Import Now
            </Link>
            <Link
              href="#login"
              className="hidden items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 sm:flex"
            >
              <UserIcon className="h-4 w-4" />
              Login
            </Link>
            <Link
              href="#cart"
              className="relative flex items-center gap-2 rounded-full bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 sm:px-4"
            >
              <CartIcon className="h-4 w-4" />
              <span className="hidden sm:inline">Cart</span>
              <span className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#c9a227] text-[10px] font-bold text-zinc-900">
                3
              </span>
            </Link>
            <button
              type="button"
              onClick={() => setMobileOpen((open) => !open)}
              className="rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-100 lg:hidden"
              aria-expanded={mobileOpen}
              aria-label={mobileOpen ? "Close menu" : "Open menu"}
            >
              {mobileOpen ? <CloseIcon className="h-5 w-5" /> : <MenuIcon className="h-5 w-5" />}
            </button>
          </div>
        </div>

        <div className="pb-3 lg:hidden">
          <SearchBar placeholder="Search products..." />
        </div>
      </div>

      {mobileOpen && (
        <nav className="border-t border-zinc-200 bg-white px-4 py-4 lg:hidden">
          <ul className="space-y-1">
            <li>
              <MegaMenu mobile onNavigate={closeMobile} />
            </li>
            {navLinks.map((link) => (
              <li key={link.href}>
                <Link
                  href={link.href}
                  onClick={closeMobile}
                  className="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100"
                >
                  {link.label}
                </Link>
              </li>
            ))}
            <li>
              <Link
                href="#order-from-china"
                onClick={closeMobile}
                className="block rounded-lg bg-[#c9a227]/10 px-3 py-2.5 text-sm font-bold text-[#8b6914]"
              >
                Import Now
              </Link>
            </li>
            <li>
              <Link
                href="#login"
                onClick={closeMobile}
                className="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100"
              >
                <UserIcon className="h-4 w-4" />
                Login
              </Link>
            </li>
          </ul>
        </nav>
      )}
    </header>
  );
}
