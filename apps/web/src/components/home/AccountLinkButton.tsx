"use client";

import Link from "next/link";
import { UserIcon } from "@/components/home/icons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";

const mobileIconButtonClass =
  "inline-flex shrink-0 items-center justify-center gap-1 rounded-lg p-2 text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 active:bg-zinc-100";

interface AccountLinkButtonProps {
  className?: string;
  iconClassName?: string;
  showLabel?: boolean;
  labelClassName?: string;
}

export function AccountLinkButton({
  className = mobileIconButtonClass,
  iconClassName = "h-5 w-5",
  showLabel = false,
  labelClassName = "text-[11px] font-semibold leading-none",
}: AccountLinkButtonProps) {
  const { isLoggedIn, isReady } = useCustomerSession();
  const href = isLoggedIn ? "/orders" : "/login";
  const label = isLoggedIn ? "Account" : "Login";

  return (
    <Link
      href={href}
      className={className}
      aria-label={isReady ? label : "Account"}
    >
      <UserIcon className={iconClassName} />
      {showLabel ? (
        <span className={labelClassName}>{isReady ? label : "Login"}</span>
      ) : (
        <span className="sr-only">{isReady ? label : "Login"}</span>
      )}
    </Link>
  );
}
