/**
 * Maps Laravel CMS homepage storefront payload → existing ResolvedHomepageContent.
 * Components stay CMS-agnostic.
 */

import type {
  CmsHomepageCta,
  CmsHomepageFeaturedContent,
  CmsHomepageFeaturedItem,
  CmsHomepageHeroSlide,
  CmsHomepageLayout,
  CmsHomepageResponse,
  CmsHomepageSection,
} from "@/lib/api/cms-homepage";
import type { TzStorefrontStore } from "@/lib/api/tz-stores";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { ApiCatalogProductCard } from "@/lib/api/products";
import type { Product } from "@/lib/types/catalog";
import type {
  HomepageCollection,
  HomepageFlashDeal,
  HomepageHeroSlide,
  HomepageSectionCopy,
  HeroSlideType,
} from "./types";
import type { ResolvedHomepageContent } from "./get-homepage-content";

const FAR_FUTURE = "2099-12-31T23:59:59.000Z";
const FAR_PAST = "2020-01-01T00:00:00.000Z";

const DEFAULT_HERO_BACKGROUNDS = [
  "bg-gradient-to-br from-zinc-950 via-zinc-900 to-[#3a1510]",
  "bg-gradient-to-br from-zinc-950 via-zinc-900 to-[#0d2a1a]",
  "bg-gradient-to-br from-zinc-950 via-zinc-900 to-[#1a2744]",
  "bg-gradient-to-br from-zinc-950 via-zinc-900 to-[#2a2410]",
] as const;

const COLLECTION_GRADIENTS = [
  "from-rose-500 to-orange-400",
  "from-violet-600 to-indigo-500",
  "from-emerald-500 to-teal-400",
  "from-amber-500 to-yellow-400",
  "from-sky-500 to-blue-600",
  "from-fuchsia-500 to-pink-500",
] as const;

export type HomepageCampaignMeta = {
  id: string;
  name: string;
  slug: string;
  priority: number;
  promotion_ids: string[];
};

export type CmsMappedHomepageFields = {
  heroSlides?: HomepageHeroSlide[];
  flashDeals?: HomepageFlashDeal[];
  collections?: HomepageCollection[];
  featuredProducts?: Product[];
  newArrivalsChina?: Product[];
  newArrivalsTz?: Product[];
  bestSellers?: Product[];
  shopByStores?: TzStorefrontStore[];
  sectionCopyOverrides?: Partial<ResolvedHomepageContent["sections"]> & {
    featuredProducts?: HomepageSectionCopy;
  };
  campaign: HomepageCampaignMeta | null;
  /** True when at least one Phase A CMS section produced usable data. */
  appliedCmsSections: boolean;
};

function resolveCtaHref(cta: CmsHomepageCta | null | undefined): string | null {
  if (!cta) return null;
  const type = (cta.type || "").toUpperCase();
  const value = (cta.value || "").trim();

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
  if (type === "PROMOTION") return "/#promotions";
  return null;
}

function mediaUrl(media: CmsHomepageHeroSlide["desktop_media"]): string | null {
  if (!media) return null;
  const url = media.url?.trim() || media.path?.trim();
  return url || null;
}

function inferHeroType(slide: CmsHomepageHeroSlide, index: number): HeroSlideType {
  const hay = `${slide.headline ?? ""} ${slide.subheadline ?? ""} ${slide.eyebrow_text ?? ""}`.toLowerCase();
  if (hay.includes("tanzania") || hay.includes("buy from tz") || hay.includes(" tz")) {
    return "tz";
  }
  if (hay.includes("sponsor") || hay.includes("partner") || hay.includes("nmb")) {
    return "sponsor";
  }
  if (hay.includes("season") || hay.includes("flash") || hay.includes("sale")) {
    return "seasonal";
  }
  if (hay.includes("china") || hay.includes("import")) {
    return "china";
  }
  const cycle: HeroSlideType[] = ["china", "tz", "sponsor", "seasonal"];
  return cycle[index % cycle.length] ?? "seasonal";
}

function accentForType(type: HeroSlideType): HomepageHeroSlide["accent"] {
  if (type === "china") return "china";
  if (type === "tz") return "tz";
  if (type === "sponsor") return "sponsor";
  return "gold";
}

export function mapCmsHeroSlide(
  slide: CmsHomepageHeroSlide,
  index: number,
): HomepageHeroSlide {
  const type = inferHeroType(slide, index);
  const primaryHref =
    resolveCtaHref(slide.primary_cta) ??
    (type === "tz" ? "/buy-from-tz" : "/products?origin=china");
  const secondaryHref = resolveCtaHref(slide.secondary_cta) ?? undefined;

  return {
    id: slide.id,
    type,
    title: (slide.headline || slide.eyebrow_text || "Featured").trim(),
    subtitle: slide.subheadline?.trim() || slide.eyebrow_text?.trim() || undefined,
    description: (slide.description || slide.subheadline || "").trim() || " ",
    ctaLabel: slide.primary_cta?.label?.trim() || "Shop now",
    ctaHref: primaryHref,
    secondaryCtaLabel: slide.secondary_cta?.label?.trim() || undefined,
    secondaryCtaHref: secondaryHref,
    desktopImageUrl: mediaUrl(slide.desktop_media),
    mobileImageUrl: mediaUrl(slide.mobile_media) ?? mediaUrl(slide.desktop_media),
    backgroundClass: DEFAULT_HERO_BACKGROUNDS[index % DEFAULT_HERO_BACKGROUNDS.length]!,
    accent: accentForType(type),
    displayStart: FAR_PAST,
    displayEnd: FAR_FUTURE,
    priority: Math.max(0, 1000 - (slide.position ?? index) * 10),
    status: "active",
  };
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null;
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === "string" && value.trim() ? value.trim() : null;
}

function numberField(data: Record<string, unknown>, key: string): number {
  const value = data[key];
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim()) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

/**
 * Normalize CMS ProductResource / card-like payloads into catalog Product.
 */
export function mapCmsProductDataToCatalogProduct(
  data: Record<string, unknown>,
): Product | null {
  const id = stringField(data, "id");
  const slug = stringField(data, "slug");
  const name = stringField(data, "name");
  if (!id || !slug || !name) return null;

  const category = asRecord(data.category);
  const brand = asRecord(data.brand);
  const channel = asRecord(data.commerce_channel);
  const images = Array.isArray(data.images) ? data.images : [];
  const primaryFromList = asRecord(images[0]);
  const primaryImage =
    asRecord(data.primary_image) ??
    (primaryFromList
      ? {
          id: stringField(primaryFromList, "id") ?? id,
          path: stringField(primaryFromList, "path") ?? "",
          url: stringField(primaryFromList, "url"),
          alt_text: stringField(primaryFromList, "alt_text"),
        }
      : null);

  const shipping = asRecord(data.shipping_prices);
  const card: ApiCatalogProductCard = {
    id,
    slug,
    name,
    short_description:
      stringField(data, "short_description") ?? stringField(data, "description"),
    price: (data.price as string | number) ?? 0,
    compare_at_price: (data.compare_at_price as string | number | null) ?? null,
    is_featured: Boolean(data.is_featured),
    primary_image: primaryImage
      ? {
          id: String(primaryImage.id ?? id),
          path: String(primaryImage.path ?? ""),
          url: (primaryImage.url as string | null | undefined) ?? null,
          alt_text: (primaryImage.alt_text as string | null | undefined) ?? null,
        }
      : null,
    category: category
      ? {
          id: stringField(category, "id") ?? "unknown",
          name: stringField(category, "name") ?? "Category",
          slug: stringField(category, "slug") ?? "uncategorized",
        }
      : null,
    brand: brand
      ? {
          id: stringField(brand, "id") ?? "unknown",
          name: stringField(brand, "name") ?? "Brand",
          slug: stringField(brand, "slug") ?? "brand",
        }
      : null,
    average_rating: numberField(data, "average_rating"),
    review_count: numberField(data, "review_count"),
    shipping_prices: {
      air:
        (shipping?.air as string | number | null | undefined) ??
        (data.air_shipping_price as string | number | null | undefined) ??
        null,
      sea:
        (shipping?.sea as string | number | null | undefined) ??
        (data.sea_shipping_price as string | number | null | undefined) ??
        null,
    },
    requires_china_shipping: data.requires_china_shipping as boolean | undefined,
    commerce_channel_code:
      stringField(channel ?? {}, "code") ??
      stringField(data, "commerce_channel_code"),
    commerce_source_label:
      stringField(data, "commerce_source_label") ??
      stringField(channel ?? {}, "customer_label") ??
      stringField(channel ?? {}, "name"),
  };

  return mapApiProductCardToCatalogProduct(card);
}

function collectFeaturedItems(
  featuredContents: CmsHomepageFeaturedContent[] | undefined,
): CmsHomepageFeaturedItem[] {
  if (!featuredContents?.length) return [];
  const sorted = [...featuredContents].sort((a, b) => a.position - b.position);
  return sorted.flatMap((block) => block.items ?? []);
}

function productsFromFeatured(
  featuredContents: CmsHomepageFeaturedContent[] | undefined,
): Product[] {
  const products: Product[] = [];
  for (const item of collectFeaturedItems(featuredContents)) {
    if ((item.item_type || "").toUpperCase() !== "PRODUCT") continue;
    const product = mapCmsProductDataToCatalogProduct(item.data ?? {});
    if (product) products.push(product);
  }
  return products;
}

function storesFromFeatured(
  featuredContents: CmsHomepageFeaturedContent[] | undefined,
): TzStorefrontStore[] {
  const stores: TzStorefrontStore[] = [];
  for (const item of collectFeaturedItems(featuredContents)) {
    if ((item.item_type || "").toUpperCase() !== "STORE") continue;
    const data = item.data ?? {};
    const id = stringField(data, "id");
    const slug = stringField(data, "slug");
    const name = stringField(data, "name");
    if (!id || !slug || !name) continue;
    stores.push({
      id,
      code: stringField(data, "code") ?? slug,
      name,
      slug,
      theme_color: stringField(data, "theme_color"),
      logo_url: stringField(data, "logo_url"),
      storefront_featured: Boolean(data.storefront_featured),
    });
  }
  return stores;
}

function collectionsFromFeatured(
  featuredContents: CmsHomepageFeaturedContent[] | undefined,
): HomepageCollection[] {
  const collections: HomepageCollection[] = [];
  let index = 0;
  for (const item of collectFeaturedItems(featuredContents)) {
    const type = (item.item_type || "").toUpperCase();
    if (type !== "CATEGORY" && type !== "BRAND") continue;
    const data = item.data ?? {};
    const id = stringField(data, "id") ?? item.id;
    const slug = stringField(data, "slug");
    const name = stringField(data, "name");
    if (!slug || !name) continue;
    collections.push({
      id,
      name,
      slug,
      description: stringField(data, "description") ?? name,
      href:
        type === "BRAND"
          ? `/products?origin=china&brand=${encodeURIComponent(slug)}`
          : `/products?origin=china&category=${encodeURIComponent(slug)}`,
      icon: type === "BRAND" ? "tag" : "grid",
      gradient: COLLECTION_GRADIENTS[index % COLLECTION_GRADIENTS.length]!,
    });
    index += 1;
  }
  return collections;
}

function flashDealsFromProducts(products: Product[]): HomepageFlashDeal[] {
  return products.slice(0, 8).map((product, index) => {
    const oldPrice = product.oldPrice > product.price ? product.oldPrice : product.price;
    const newPrice =
      product.oldPrice > product.price ? product.price : Math.max(0, product.price * 0.9);
    return {
      id: `cms-flash-${product.catalogProductId ?? product.id}`,
      productSlug: product.slug,
      title: product.name,
      imageUrl: product.image ?? product.primary_image?.url ?? null,
      imageEmoji: product.emoji,
      oldPrice,
      newPrice,
      href: `/products/${product.slug}`,
      endsAt: FAR_FUTURE,
      displayStart: FAR_PAST,
      displayEnd: FAR_FUTURE,
      origin: product.origin,
      status: "active" as const,
      priority: 100 - index,
    };
  });
}

function sectionCopyFrom(
  section: CmsHomepageSection,
  featured: CmsHomepageFeaturedContent[] | undefined,
  fallback: HomepageSectionCopy,
): HomepageSectionCopy {
  const first = featured?.[0];
  return {
    eyebrow: fallback.eyebrow,
    title: section.title?.trim() || first?.title?.trim() || fallback.title,
    description:
      section.subtitle?.trim() || first?.subtitle?.trim() || fallback.description,
    viewAllLabel: fallback.viewAllLabel,
    viewAllHref: fallback.viewAllHref,
  };
}

function campaignFromMeta(
  response: CmsHomepageResponse,
): HomepageCampaignMeta | null {
  const campaign = response.meta?.campaign;
  if (!campaign?.id) return null;
  return {
    id: campaign.id,
    name: campaign.name,
    slug: campaign.slug,
    priority: campaign.priority,
    promotion_ids: campaign.promotion_ids ?? [],
  };
}

/**
 * Pure mapper: CMS response + seed base → partial CMS overrides.
 * Returns appliedCmsSections=false when layout is empty / unusable for Phase A.
 */
export function mapCmsHomepageResponse(
  response: CmsHomepageResponse,
  seedBase: ResolvedHomepageContent,
): CmsMappedHomepageFields {
  const campaign = campaignFromMeta(response);
  const layout: CmsHomepageLayout | null = response.data;

  if (!layout?.sections?.length) {
    return { campaign, appliedCmsSections: false };
  }

  const result: CmsMappedHomepageFields = {
    campaign,
    appliedCmsSections: false,
    sectionCopyOverrides: {},
  };

  const sections = [...layout.sections].sort((a, b) => a.position - b.position);

  for (const section of sections) {
    const type = (section.section_type || "").toUpperCase();

    if (type === "HERO") {
      const slides = [...(section.hero_slides ?? [])]
        .sort((a, b) => a.position - b.position)
        .map((slide, index) => mapCmsHeroSlide(slide, index));
      if (slides.length > 0) {
        result.heroSlides = slides;
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "FEATURED_PRODUCTS") {
      const products = productsFromFeatured(section.featured_contents);
      if (products.length > 0) {
        result.featuredProducts = products;
        result.sectionCopyOverrides!.featuredProducts = sectionCopyFrom(
          section,
          section.featured_contents,
          {
            eyebrow: "Curated Selection",
            title: "Featured Products",
            description:
              "Hand-picked premium products with factory-direct pricing — updated daily from our verified supplier network.",
            viewAllLabel: "View all",
            viewAllHref: "/products",
          },
        );
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "NEW_ARRIVALS") {
      const products = productsFromFeatured(section.featured_contents);
      if (products.length > 0) {
        result.newArrivalsChina = products.filter((p) => p.origin === "china");
        result.newArrivalsTz = products.filter((p) => p.origin === "tz");
        // If origin unknown/mixed poorly, put remainder into china bucket for visibility.
        if (
          result.newArrivalsChina.length === 0 &&
          result.newArrivalsTz.length === 0
        ) {
          result.newArrivalsChina = products;
        }
        result.sectionCopyOverrides!.newArrivals = sectionCopyFrom(
          section,
          section.featured_contents,
          seedBase.sections.newArrivals,
        );
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "BEST_SELLERS") {
      const products = productsFromFeatured(section.featured_contents);
      if (products.length > 0) {
        result.bestSellers = products.map((product) => ({
          ...product,
          badges: Array.from(new Set([...(product.badges || []), "BEST SELLER" as const])),
        }));
        result.sectionCopyOverrides!.bestSellers = sectionCopyFrom(
          section,
          section.featured_contents,
          seedBase.sections.bestSellers,
        );
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "SHOP_BY_STORE") {
      const stores = storesFromFeatured(section.featured_contents);
      if (stores.length > 0) {
        result.shopByStores = stores;
        result.sectionCopyOverrides!.shopByStore = sectionCopyFrom(
          section,
          section.featured_contents,
          seedBase.sections.shopByStore,
        );
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "FEATURED_COLLECTIONS") {
      const collections = collectionsFromFeatured(section.featured_contents);
      if (collections.length > 0) {
        result.collections = collections;
        result.sectionCopyOverrides!.collections = sectionCopyFrom(
          section,
          section.featured_contents,
          seedBase.sections.collections,
        );
        result.appliedCmsSections = true;
      }
      continue;
    }

    if (type === "FLASH_DEALS") {
      const products = productsFromFeatured(section.featured_contents);
      if (products.length > 0) {
        result.flashDeals = flashDealsFromProducts(products);
        result.sectionCopyOverrides!.flashDeals = sectionCopyFrom(
          section,
          section.featured_contents,
          seedBase.sections.flashDeals,
        );
        result.appliedCmsSections = true;
      }
    }
  }

  return result;
}

/**
 * Merge CMS mapped fields onto seed base. Static seed areas always remain.
 */
export function mergeCmsMappedIntoSeed(
  seedBase: ResolvedHomepageContent,
  mapped: CmsMappedHomepageFields,
): ResolvedHomepageContent {
  return {
    ...seedBase,
    heroSlides: mapped.heroSlides ?? seedBase.heroSlides,
    flashDeals: mapped.flashDeals ?? seedBase.flashDeals,
    collections: mapped.collections ?? seedBase.collections,
    sections: {
      ...seedBase.sections,
      ...(mapped.sectionCopyOverrides?.flashDeals
        ? { flashDeals: mapped.sectionCopyOverrides.flashDeals }
        : {}),
      ...(mapped.sectionCopyOverrides?.newArrivals
        ? { newArrivals: mapped.sectionCopyOverrides.newArrivals }
        : {}),
      ...(mapped.sectionCopyOverrides?.bestSellers
        ? { bestSellers: mapped.sectionCopyOverrides.bestSellers }
        : {}),
      ...(mapped.sectionCopyOverrides?.collections
        ? { collections: mapped.sectionCopyOverrides.collections }
        : {}),
      ...(mapped.sectionCopyOverrides?.shopByStore
        ? { shopByStore: mapped.sectionCopyOverrides.shopByStore }
        : {}),
    },
    featuredProducts: mapped.featuredProducts,
    featuredProductsCopy: mapped.sectionCopyOverrides?.featuredProducts,
    newArrivalsChina: mapped.newArrivalsChina,
    newArrivalsTz: mapped.newArrivalsTz,
    bestSellers: mapped.bestSellers,
    shopByStores: mapped.shopByStores,
    campaign: mapped.campaign,
    source: mapped.appliedCmsSections ? "cms" : "fallback",
  };
}
