/**
 * Storefront navigation resolver.
 *
 * Priority: CMS Navigation API → navigation-policy / home-data fallback.
 * CMS failure must not break the website.
 */

import type {
  CmsNavAudience,
  CmsNavigationAllPayload,
  CmsNavigationItem,
  CmsNavigationShellPayload,
  CmsNavCta,
  CmsNavMegaMenu,
} from "@/lib/api/cms-navigation";
import { footerLinks } from "@/lib/home-data";
import {
  getMobileDrawerItems,
  getPrimaryNavItems,
  type StorefrontNavAudience,
  type StorefrontNavItem,
  type StorefrontNavItemId,
} from "@/lib/storefront/navigation-policy";

export type ResolvedNavKind = "china_mega" | "tz_mega" | "link" | "group";

export type ResolvedNavStore = {
  id: string;
  name: string;
  slug: string;
  logo_url?: string | null;
};

export type ResolvedNavItem = {
  key: string;
  kind: ResolvedNavKind;
  label: string;
  href?: string;
  icon?: string | null;
  policyId?: StorefrontNavItemId | null;
  journey?: "CHINA_IMPORT" | "TZ_LOCAL";
  stores?: ResolvedNavStore[];
  children?: ResolvedNavItem[];
};

export type ResolvedFooterColumn = {
  key: string;
  title: string;
  links: Array<{ label: string; href: string }>;
};

export type ResolvedStorefrontNavigation = {
  source: "cms" | "fallback";
  primary: ResolvedNavItem[];
  mobile: ResolvedNavItem[];
  footerColumns: ResolvedFooterColumn[];
  /** TZ stores for footer Buy From TZ when CMS/engine hydrated them */
  footerTzStores: ResolvedNavStore[];
};

export function mapAudienceToCmsAudience(
  audience: StorefrontNavAudience,
): CmsNavAudience {
  if (audience.role === "staff") return "admin_preview";
  if (audience.isAuthenticated) return "authenticated";
  return "guest";
}

export function resolveCtaHref(cta: CmsNavCta | null | undefined): string | null {
  if (!cta) return null;
  const type = (cta.type || "").toUpperCase();
  const value = cta.value?.trim() || "";

  if (type === "URL" && cta.url) return cta.url;
  if (type === "URL" && value) return value;
  if (type === "PAGE" && value) return value.startsWith("/") ? value : `/${value}`;
  if (type === "PRODUCT" && value) return `/products/${encodeURIComponent(value)}`;
  if (type === "STORE" && value) return `/buy-from-tz/${encodeURIComponent(value)}`;
  if (type === "CATEGORY" && value) {
    return `/products?origin=china&category=${encodeURIComponent(value)}`;
  }
  if (type === "BRAND" && value) {
    return `/products?origin=china&brand=${encodeURIComponent(value)}`;
  }
  if (type === "CHINA_ORDER_FORM") return "/products?origin=china";
  if (type === "PROMOTION" && value) return `/#promotions`;
  return null;
}

function inferPolicyId(label: string, href: string | null): StorefrontNavItemId | null {
  const hay = `${label} ${href ?? ""}`.toLowerCase();
  if (hay.includes("my orders") || href === "/orders") return "myOrders";
  if (hay.includes("about")) return "aboutUs";
  if (hay.includes("contact")) return "contactUs";
  if (hay.includes("sign in") || href === "/login") return "signIn";
  if (hay.includes("create account") || href === "/register") return "createAccount";
  if (hay.includes("my account") || href === "/account") return "myAccount";
  if (hay.includes("sign out")) return "signOut";
  if (hay.includes("notification")) return "notifications";
  return null;
}

function journeyCode(item: CmsNavigationItem): "CHINA_IMPORT" | "TZ_LOCAL" | null {
  const raw =
    item.journey?.code ||
    item.mega_menu?.journey ||
    item.target_value ||
    "";
  if (raw === "TZ_LOCAL") return "TZ_LOCAL";
  if (raw === "CHINA_IMPORT") return "CHINA_IMPORT";
  return null;
}

function storesFromMega(mega?: CmsNavMegaMenu): ResolvedNavStore[] {
  if (!mega?.stores?.length) return [];
  return mega.stores.map((store) => ({
    id: store.id,
    name: store.name,
    slug: store.slug,
    logo_url: store.logo_url ?? null,
  }));
}

export function mapCmsItemToResolved(item: CmsNavigationItem): ResolvedNavItem {
  const type = (item.item_type || "").toUpperCase();
  const children = (item.children ?? []).map(mapCmsItemToResolved);

  if (type === "GROUP") {
    return {
      key: item.id,
      kind: "group",
      label: item.title,
      icon: item.icon,
      children,
    };
  }

  const journey = journeyCode(item);
  if (type === "JOURNEY" || type === "MEGA_MENU") {
    if (journey === "TZ_LOCAL") {
      return {
        key: item.id,
        kind: "tz_mega",
        label: item.title,
        icon: item.icon,
        policyId: "buyFromTz",
        journey: "TZ_LOCAL",
        stores: storesFromMega(item.mega_menu),
        children,
      };
    }
    return {
      key: item.id,
      kind: "china_mega",
      label: item.title,
      icon: item.icon,
      policyId: "orderFromChina",
      journey: "CHINA_IMPORT",
      children,
    };
  }

  const href = resolveCtaHref(item.cta) ?? "#";
  return {
    key: item.id,
    kind: "link",
    label: item.title,
    href,
    icon: item.icon,
    policyId: inferPolicyId(item.title, href),
    children,
  };
}

export function mapCmsShellItems(items: CmsNavigationItem[] | undefined): ResolvedNavItem[] {
  return (items ?? []).map(mapCmsItemToResolved);
}

export function policyItemToResolved(item: StorefrontNavItem): ResolvedNavItem {
  if (item.id === "orderFromChina") {
    return {
      key: item.id,
      kind: "china_mega",
      label: item.label,
      policyId: item.id,
      journey: "CHINA_IMPORT",
    };
  }
  if (item.id === "buyFromTz") {
    return {
      key: item.id,
      kind: "tz_mega",
      label: item.label,
      policyId: item.id,
      journey: "TZ_LOCAL",
    };
  }
  return {
    key: item.id,
    kind: "link",
    label: item.label,
    href: item.href,
    policyId: item.id,
  };
}

function shellHasItems(shell: CmsNavigationShellPayload | undefined): boolean {
  return Boolean(shell?.shell && (shell.items?.length ?? 0) > 0);
}

function fallbackFooterColumns(): ResolvedFooterColumn[] {
  return [
    {
      key: "about",
      title: "About",
      links: footerLinks.about.map((l) => ({ label: l.label, href: l.href })),
    },
    {
      key: "contact",
      title: "Contact",
      links: footerLinks.contact.map((l) => ({ label: l.label, href: l.href })),
    },
    {
      key: "quickLinks",
      title: "Quick Links",
      links: footerLinks.quickLinks.map((l) => ({ label: l.label, href: l.href })),
    },
    {
      key: "buyFromTz",
      title: "Buy From TZ",
      links: footerLinks.buyFromTz.map((l) => ({ label: l.label, href: l.href })),
    },
  ];
}

function footerFromCmsItems(items: ResolvedNavItem[]): {
  columns: ResolvedFooterColumn[];
  tzStores: ResolvedNavStore[];
} {
  const columns: ResolvedFooterColumn[] = [];
  const tzStores: ResolvedNavStore[] = [];

  for (const item of items) {
    if (item.kind === "group") {
      const links = (item.children ?? [])
        .filter((child) => child.kind === "link" && child.href)
        .map((child) => ({ label: child.label, href: child.href! }));
      if (links.length > 0) {
        columns.push({ key: item.key, title: item.label, links });
      }
      continue;
    }

    if (item.kind === "tz_mega") {
      if (item.stores?.length) {
        tzStores.push(...item.stores);
      }
      columns.push({
        key: item.key,
        title: item.label || "Buy From TZ",
        links: [
          { label: "All stores", href: "/buy-from-tz" },
          ...(item.stores ?? []).map((store) => ({
            label: store.name,
            href: `/buy-from-tz/${store.slug}`,
          })),
        ],
      });
      continue;
    }

    if (item.kind === "china_mega") {
      columns.push({
        key: item.key,
        title: item.label,
        links: [{ label: item.label, href: "/products?origin=china" }],
      });
      continue;
    }

    if (item.kind === "link" && item.href) {
      // Collect orphan links into a Quick Links style column later
      columns.push({
        key: item.key,
        title: item.label,
        links: [{ label: item.label, href: item.href }],
      });
    }
  }

  return { columns, tzStores };
}

/**
 * Pure resolver: CMS payload → UI model, with policy fallback.
 */
export function resolveStorefrontNavigation(
  cms: CmsNavigationAllPayload | null | undefined,
  audience: StorefrontNavAudience,
): ResolvedStorefrontNavigation {
  const primaryShell = cms?.shells?.PRIMARY;
  const mobileShell = cms?.shells?.MOBILE;
  const footerShell = cms?.shells?.FOOTER;

  const primaryFromCms = shellHasItems(primaryShell)
    ? mapCmsShellItems(primaryShell!.items)
    : null;
  const mobileFromCms = shellHasItems(mobileShell)
    ? mapCmsShellItems(mobileShell!.items)
    : null;
  const footerFromCms = shellHasItems(footerShell)
    ? mapCmsShellItems(footerShell!.items)
    : null;

  const primary =
    primaryFromCms ?? getPrimaryNavItems(audience).map(policyItemToResolved);

  let mobile: ResolvedNavItem[];
  if (mobileFromCms) {
    mobile = mobileFromCms;
  } else if (primaryFromCms) {
    // Reuse CMS primary chrome; append auth actions from policy when missing.
    const extras = getMobileDrawerItems(audience)
      .filter((item) =>
        ["signIn", "createAccount", "notifications", "myAccount", "signOut"].includes(
          item.id,
        ),
      )
      .map(policyItemToResolved);
    const existing = new Set(
      primaryFromCms.map((item) => item.policyId).filter(Boolean),
    );
    mobile = [
      ...primaryFromCms,
      ...extras.filter((item) => !item.policyId || !existing.has(item.policyId)),
    ];
  } else {
    mobile = getMobileDrawerItems(audience).map(policyItemToResolved);
  }

  let footerColumns: ResolvedFooterColumn[];
  let footerTzStores: ResolvedNavStore[] = [];
  let footerSourceCms = false;

  if (footerFromCms) {
    const mapped = footerFromCmsItems(footerFromCms);
    footerColumns = mapped.columns.length > 0 ? mapped.columns : fallbackFooterColumns();
    footerTzStores = mapped.tzStores;
    footerSourceCms = mapped.columns.length > 0;
  } else {
    footerColumns = fallbackFooterColumns();
  }

  const usedCms = Boolean(primaryFromCms || mobileFromCms || footerSourceCms);

  return {
    source: usedCms ? "cms" : "fallback",
    primary,
    mobile,
    footerColumns,
    footerTzStores,
  };
}
