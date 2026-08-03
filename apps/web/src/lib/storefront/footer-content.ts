import { buyFromTzBrandMenu } from "@/lib/catalog/brands";
import type { ResolvedFooterColumn } from "@/lib/storefront/resolve-storefront-navigation";

export const FOOTER_CONTACT_LINKS = [
  { label: "support@chinaordertz.com", href: "mailto:support@chinaordertz.com" },
  { label: "+255 724 557 711", href: "tel:+255724557711" },
  { label: "Dar es Salaam, Tanzania", href: "/#contact" },
] as const;

export const FOOTER_ABOUT_LINKS = [
  { label: "Our Story", href: "/#about" },
  { label: "Why Choose Us", href: "/#about" },
  { label: "How It Works", href: "/#how-it-works" },
] as const;

export const FOOTER_TZ_STORE_SLUG_ORDER = [
  "rovi-beauty",
  "zion-mode",
  "tzur-jewelry",
  "peachy-lingerie",
] as const;

export const FOOTER_BRAND_CREDIT = {
  line1: "Created and Designed by ROGGY TECH",
  phone: "+255 743 964 569",
  phoneHref: "tel:+255743964569",
} as const;

export function sortFooterTzStores<T extends { slug: string }>(stores: T[]): T[] {
  const order = new Map(FOOTER_TZ_STORE_SLUG_ORDER.map((slug, index) => [slug, index]));

  return [...stores].sort(
    (a, b) => (order.get(a.slug as (typeof FOOTER_TZ_STORE_SLUG_ORDER)[number]) ?? 999)
      - (order.get(b.slug as (typeof FOOTER_TZ_STORE_SLUG_ORDER)[number]) ?? 999),
  );
}

export function buildFooterBuyFromTzLinks(
  stores: Array<{ name: string; slug: string }>,
): Array<{ label: string; href: string }> {
  return sortFooterTzStores(stores).map((store) => ({
    label: store.name,
    href: `/buy-from-tz/${store.slug}`,
  }));
}

export function defaultFooterBuyFromTzLinks(): Array<{ label: string; href: string }> {
  return buildFooterBuyFromTzLinks(
    FOOTER_TZ_STORE_SLUG_ORDER.map((slug) => {
      const brand = buyFromTzBrandMenu.find((item) => item.slug === slug);
      if (!brand) {
        throw new Error(`Missing footer TZ store brand for slug: ${slug}`);
      }

      return { name: brand.name, slug: brand.slug };
    }),
  );
}

export function normalizeFooterColumn(column: ResolvedFooterColumn): ResolvedFooterColumn {
  const title = column.title.toLowerCase();

  if (title.includes("contact")) {
    return { ...column, links: [...FOOTER_CONTACT_LINKS] };
  }

  if (title.includes("about")) {
    return { ...column, links: [...FOOTER_ABOUT_LINKS] };
  }

  return column;
}

export function hasRealSocialHref(href: string): boolean {
  const trimmed = href.trim();

  return trimmed.length > 0 && trimmed !== "#" && !trimmed.startsWith("#");
}
