"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { AccountMenu } from "@/components/customer/AccountMenu";
import {
  buildCurrentReturnPath,
  buildLoginHref,
  buildRegisterHref,
} from "@/lib/auth/return-url";
import { STOREFRONT_NAV_LABELS, type StorefrontNavAudience } from "@/lib/storefront/navigation-policy";
import {
  isAccountMenuPresentation,
  resolveAccountMenuPresentation,
} from "@/lib/storefront/account-menu-presentation";
import { storefrontTypography } from "@/lib/storefront/typography";

type AccountNavigationProps = {
  audience: StorefrontNavAudience;
  variant: "desktop" | "mobile";
  className?: string;
  iconClassName?: string;
  labelClassName?: string;
};

/**
 * Guest: Sign In + Create Account links (no My Orders / notifications).
 * Customer: My Account dropdown (existing AccountMenu).
 */
export function AccountNavigation({
  audience,
  variant,
  className,
  iconClassName,
  labelClassName,
}: AccountNavigationProps) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const returnPath = buildCurrentReturnPath(pathname, searchParams.toString());
  const presentation = resolveAccountMenuPresentation(audience, variant);

  if (!isAccountMenuPresentation(presentation)) {
    return (
      <div className="flex shrink-0 items-center gap-3 lg:gap-4">
        <Link
          href={buildLoginHref(returnPath)}
          className={`inline-flex h-[52px] items-center whitespace-nowrap ${storefrontTypography.navSecondary} transition-colors hover:text-zinc-900`}
        >
          {STOREFRONT_NAV_LABELS.signIn}
        </Link>
        <Link
          href={buildRegisterHref(returnPath)}
          className={`inline-flex h-10 items-center whitespace-nowrap rounded-full bg-zinc-900 px-4 text-white transition hover:bg-[#c9a227] hover:text-zinc-900 ${storefrontTypography.button}`}
        >
          {STOREFRONT_NAV_LABELS.createAccount}
        </Link>
      </div>
    );
  }

  return (
    <AccountMenu
      showLabel={presentation.showLabel}
      guestBehavior={presentation.guestBehavior}
      preset={presentation.preset}
      className={className}
      iconClassName={iconClassName}
      labelClassName={labelClassName}
    />
  );
}
