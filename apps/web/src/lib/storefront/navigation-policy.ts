/**
 * Storefront navigation visibility policy.
 * UI hiding is not authorization — order routes remain protected server-side.
 *
 * Sprint 5B: primary source of truth for chrome is CMS Navigation API via
 * `resolve-storefront-navigation.ts`. This module remains the offline/empty-shell fallback.
 */

export const STOREFRONT_NAV_LABELS = {
  orderFromChina: "Order from China",
  buyFromTz: "Buy from TZ",
  myOrders: "My Orders",
  aboutUs: "About Us",
  contactUs: "Contact Us",
  signIn: "Sign In",
  createAccount: "Create Account",
  myAccount: "My Account",
  signOut: "Sign Out",
  notifications: "Notifications",
} as const;

export type StorefrontNavRole = "guest" | "customer" | "staff";

export type StorefrontNavAudience = {
  role: StorefrontNavRole;
  isAuthenticated: boolean;
  /** Notifications feature available for authenticated customers */
  notificationsEnabled?: boolean;
};

export type StorefrontNavItemId =
  | "orderFromChina"
  | "buyFromTz"
  | "myOrders"
  | "aboutUs"
  | "contactUs"
  | "signIn"
  | "createAccount"
  | "myAccount"
  | "signOut"
  | "notifications"
  | "search"
  | "cart";

export type StorefrontNavItem = {
  id: StorefrontNavItemId;
  label: string;
  href: string;
};

export type StorefrontActiveJourney = "china" | "tz" | "orders" | null;

const PUBLIC_JOURNEYS: StorefrontNavItem[] = [
  {
    id: "orderFromChina",
    label: STOREFRONT_NAV_LABELS.orderFromChina,
    href: "/products?origin=china",
  },
  {
    id: "buyFromTz",
    label: STOREFRONT_NAV_LABELS.buyFromTz,
    href: "/buy-from-tz",
  },
];

const COMPANY_LINKS: StorefrontNavItem[] = [
  { id: "aboutUs", label: STOREFRONT_NAV_LABELS.aboutUs, href: "/#about" },
  { id: "contactUs", label: STOREFRONT_NAV_LABELS.contactUs, href: "/#contact" },
];

/** Resolve storefront audience from customer session (staff use admin shells). */
export function resolveStorefrontNavAudience(input: {
  isLoggedIn: boolean;
  isStaffPreview?: boolean;
}): StorefrontNavAudience {
  if (input.isStaffPreview) {
    return { role: "staff", isAuthenticated: true, notificationsEnabled: false };
  }

  if (input.isLoggedIn) {
    return { role: "customer", isAuthenticated: true, notificationsEnabled: true };
  }

  return { role: "guest", isAuthenticated: false, notificationsEnabled: false };
}

/** Primary center nav items (journeys + optional My Orders + company). */
export function getPrimaryNavItems(audience: StorefrontNavAudience): StorefrontNavItem[] {
  const items = [...PUBLIC_JOURNEYS];

  if (audience.role === "customer" || audience.role === "staff") {
    items.push({
      id: "myOrders",
      label: STOREFRONT_NAV_LABELS.myOrders,
      href: "/orders",
    });
  }

  return [...items, ...COMPANY_LINKS];
}

/** Right-rail / account actions that appear in the header chrome. */
export function getHeaderAccountActions(audience: StorefrontNavAudience): StorefrontNavItem[] {
  if (audience.role === "guest") {
    return [
      { id: "signIn", label: STOREFRONT_NAV_LABELS.signIn, href: "/login" },
      { id: "createAccount", label: STOREFRONT_NAV_LABELS.createAccount, href: "/register" },
    ];
  }

  const actions: StorefrontNavItem[] = [];

  if (audience.notificationsEnabled) {
    actions.push({
      id: "notifications",
      label: STOREFRONT_NAV_LABELS.notifications,
      href: "/account/notifications",
    });
  }

  actions.push({
    id: "myAccount",
    label: STOREFRONT_NAV_LABELS.myAccount,
    href: "/account",
  });

  return actions;
}

/** Mobile drawer rows (excludes search/cart chrome). */
export function getMobileDrawerItems(audience: StorefrontNavAudience): StorefrontNavItem[] {
  const items = getPrimaryNavItems(audience);

  if (audience.role === "guest") {
    return [
      ...items,
      { id: "signIn", label: STOREFRONT_NAV_LABELS.signIn, href: "/login" },
      { id: "createAccount", label: STOREFRONT_NAV_LABELS.createAccount, href: "/register" },
    ];
  }

  const authExtras: StorefrontNavItem[] = [];

  if (audience.notificationsEnabled) {
    authExtras.push({
      id: "notifications",
      label: STOREFRONT_NAV_LABELS.notifications,
      href: "/account/notifications",
    });
  }

  authExtras.push(
    { id: "myAccount", label: STOREFRONT_NAV_LABELS.myAccount, href: "/account" },
    { id: "signOut", label: STOREFRONT_NAV_LABELS.signOut, href: "#sign-out" },
  );

  return [...items, ...authExtras];
}

export function shouldShowMyOrders(audience: StorefrontNavAudience): boolean {
  return audience.role === "customer" || audience.role === "staff";
}

export function shouldShowGuestAuthActions(audience: StorefrontNavAudience): boolean {
  return audience.role === "guest";
}

export function shouldShowNotifications(audience: StorefrontNavAudience): boolean {
  return Boolean(audience.isAuthenticated && audience.notificationsEnabled);
}

/** Map pathname (+ search) to the active commerce journey for nav highlighting. */
export function resolveActiveJourney(
  pathname: string,
  search = "",
): StorefrontActiveJourney {
  const path = pathname.toLowerCase();
  const params = new URLSearchParams(search.startsWith("?") ? search.slice(1) : search);
  const origin = (params.get("origin") || "").toLowerCase();

  if (
    path.startsWith("/orders") ||
    path.startsWith("/track") ||
    path.startsWith("/track-order")
  ) {
    return "orders";
  }

  if (
    path.startsWith("/buy-from-tz") ||
    origin === "tz" ||
    origin === "local" ||
    origin === "dar"
  ) {
    return "tz";
  }

  if (
    path.startsWith("/products") ||
    path.startsWith("/categories") ||
    path.startsWith("/brand/") ||
    origin === "china"
  ) {
    // TZ product pages under buy-from-tz already matched above.
    if (path.includes("/buy-from-tz")) {
      return "tz";
    }
    return "china";
  }

  return null;
}

export function isNavItemActive(
  itemId: StorefrontNavItemId,
  activeJourney: StorefrontActiveJourney,
): boolean {
  if (itemId === "orderFromChina") return activeJourney === "china";
  if (itemId === "buyFromTz") return activeJourney === "tz";
  if (itemId === "myOrders") return activeJourney === "orders";
  return false;
}

/** Normalize API order source values ("China"/"Dar"/"china"/"local") for UI. */
export function normalizeCommerceSource(
  source: string | null | undefined,
): "china" | "tz" | null {
  if (!source) return null;
  const value = source.trim().toLowerCase();
  if (value === "china" || value === "china_import" || value === "china import") {
    return "china";
  }
  if (
    value === "dar" ||
    value === "local" ||
    value === "tz" ||
    value === "tz_local" ||
    value === "tanzania" ||
    value === "tanzania store"
  ) {
    return "tz";
  }
  return null;
}

export function commerceSourceLabel(source: string | null | undefined): {
  short: string;
  journey: string;
} | null {
  const normalized = normalizeCommerceSource(source);
  if (normalized === "china") {
    return { short: "China Import", journey: STOREFRONT_NAV_LABELS.orderFromChina };
  }
  if (normalized === "tz") {
    return { short: "Tanzania Store", journey: STOREFRONT_NAV_LABELS.buyFromTz };
  }
  return null;
}
