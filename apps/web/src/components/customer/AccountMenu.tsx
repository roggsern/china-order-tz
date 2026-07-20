"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { UserIcon } from "@/components/home/icons";
import { resolveCustomerDisplayName } from "@/lib/customer/display-name";
import { logoutCustomer } from "@/lib/customer/logout-customer";
import { useCustomerSession } from "@/lib/customer/use-customer-session";

const mobileIconButtonClass =
  "inline-flex shrink-0 items-center justify-center gap-1 rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 active:bg-zinc-100";

type AccountMenuItem =
  | { type: "link"; label: string; href: string }
  | { type: "logout"; label: string };

const ACCOUNT_MENU_ITEMS: AccountMenuItem[] = [
  { type: "link", label: "My Account", href: "/account" },
  { type: "link", label: "My Orders", href: "/orders" },
  { type: "link", label: "Wishlist", href: "/wishlist" },
  { type: "link", label: "Saved Addresses", href: "/account/addresses" },
  { type: "link", label: "Loyalty & Rewards", href: "/account/loyalty" },
  { type: "link", label: "Notifications", href: "/account/notifications" },
  { type: "link", label: "Settings", href: "/account" },
  { type: "logout", label: "Sign Out" },
];

function ChevronDownIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
  );
}

function MenuDivider() {
  return <div className="my-1.5 border-t border-zinc-100" role="separator" />;
}

interface AccountMenuProps {
  className?: string;
  iconClassName?: string;
  showLabel?: boolean;
  labelClassName?: string;
}

export function AccountMenu({
  className = mobileIconButtonClass,
  iconClassName = "h-5 w-5",
  showLabel = false,
  labelClassName = "text-[11px] font-semibold leading-none",
}: AccountMenuProps) {
  const router = useRouter();
  const menuId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const { session, isLoggedIn, isReady } = useCustomerSession();

  const label = isLoggedIn ? "My Account" : "Sign In";
  const displayName = resolveCustomerDisplayName(session?.name, session?.email);

  const closeMenu = useCallback(() => {
    setOpen(false);
  }, []);

  const handleLogout = useCallback(() => {
    closeMenu();
    logoutCustomer();
    router.push("/");
  }, [closeMenu, router]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        closeMenu();
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        closeMenu();
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    document.addEventListener("keydown", handleEscape);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      document.removeEventListener("keydown", handleEscape);
    };
  }, [closeMenu, open]);

  useEffect(() => {
    if (!isLoggedIn) {
      closeMenu();
    }
  }, [closeMenu, isLoggedIn]);

  if (!isLoggedIn) {
    return (
      <Link
        href="/login"
        className={className}
        aria-label={isReady ? "Sign In" : "Account"}
      >
        <UserIcon className={iconClassName} />
        {showLabel ? (
          <span className={labelClassName}>Sign In</span>
        ) : (
          <span className="sr-only">Sign In</span>
        )}
      </Link>
    );
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        className={className}
        aria-label={label}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-controls={menuId}
        onClick={() => setOpen((current) => !current)}
      >
        <UserIcon className={iconClassName} />
        {showLabel ? (
          <span className={labelClassName}>{label}</span>
        ) : (
          <span className="sr-only">{label}</span>
        )}
        <ChevronDownIcon
          className={`hidden h-3.5 w-3.5 shrink-0 text-zinc-400 transition-transform sm:block ${
            open ? "rotate-180" : ""
          }`}
          aria-hidden
        />
      </button>

      {open ? (
        <div
          id={menuId}
          role="menu"
          aria-label="Account menu"
          className="absolute right-0 top-[calc(100%+0.5rem)] z-[70] w-72 max-w-[calc(100vw-1rem)] overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.12)] animate-fade-in"
        >
          <div className="border-b border-zinc-100 bg-zinc-50/80 px-4 py-3.5">
            <p className="truncate text-sm font-bold text-zinc-900">{displayName}</p>
            {session?.email ? (
              <p className="mt-0.5 truncate text-xs text-zinc-500">{session.email}</p>
            ) : null}
          </div>

          <div className="p-1.5">
            {ACCOUNT_MENU_ITEMS.map((item) => {
              if (item.type === "link") {
                return (
                  <Link
                    key={item.label}
                    href={item.href}
                    role="menuitem"
                    className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-900"
                    onClick={closeMenu}
                  >
                    {item.label}
                  </Link>
                );
              }

              return (
                <div key={item.label}>
                  <MenuDivider />
                  <button
                    type="button"
                    role="menuitem"
                    className="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    onClick={handleLogout}
                  >
                    {item.label}
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
