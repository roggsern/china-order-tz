import { STOREFRONT_NAV_LABELS } from "./navigation-policy";

export type AccountMenuLinkItem = { type: "link"; label: string; href: string };
export type AccountMenuLogoutItem = { type: "logout"; label: string };
export type AccountMenuItem = AccountMenuLinkItem | AccountMenuLogoutItem;

export type AccountMenuPreset = "full" | "header";

export const GUEST_ACCOUNT_MENU_ITEMS: AccountMenuLinkItem[] = [
  { type: "link", label: STOREFRONT_NAV_LABELS.signIn, href: "/login" },
  { type: "link", label: STOREFRONT_NAV_LABELS.createAccount, href: "/register" },
];

export const MOBILE_HEADER_ACCOUNT_MENU_ITEMS: AccountMenuItem[] = [
  { type: "link", label: STOREFRONT_NAV_LABELS.myAccount, href: "/account" },
  { type: "link", label: STOREFRONT_NAV_LABELS.myOrders, href: "/orders" },
  { type: "link", label: "Profile", href: "/account/security" },
  { type: "logout", label: STOREFRONT_NAV_LABELS.signOut },
];

export const FULL_ACCOUNT_MENU_ITEMS: AccountMenuItem[] = [
  { type: "link", label: STOREFRONT_NAV_LABELS.myAccount, href: "/account" },
  { type: "link", label: STOREFRONT_NAV_LABELS.myOrders, href: "/orders" },
  { type: "link", label: "Wishlist", href: "/wishlist" },
  { type: "link", label: "Saved Addresses", href: "/account/addresses" },
  { type: "link", label: "Loyalty & Rewards", href: "/account/loyalty" },
  { type: "link", label: "Support", href: "/account/support" },
  { type: "link", label: "Notifications", href: "/account/notifications" },
  { type: "link", label: "Security", href: "/account/security" },
  { type: "logout", label: STOREFRONT_NAV_LABELS.signOut },
];

export function resolveAuthenticatedAccountMenuItems(options: {
  preset: AccountMenuPreset;
  wishlistEnabled: boolean;
  featuresReady: boolean;
}): AccountMenuItem[] {
  if (options.preset === "header") {
    return MOBILE_HEADER_ACCOUNT_MENU_ITEMS;
  }

  return FULL_ACCOUNT_MENU_ITEMS.filter(
    (item) =>
      item.type !== "link" ||
      item.href !== "/wishlist" ||
      !options.featuresReady ||
      options.wishlistEnabled,
  );
}

export function resolveGuestAccountMenuItems(): AccountMenuLinkItem[] {
  return GUEST_ACCOUNT_MENU_ITEMS;
}
