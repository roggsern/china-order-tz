import type {
  ApiCatalogImage,
  ApiCatalogProductCard,
  ApiCatalogProductDetail,
  ApiCatalogProductVariant,
  ApiCatalogStockSource,
} from "@/lib/api/products";
import { resolveProductBadges } from "@/lib/catalog/badges";
import { getDefaultFlatShippingDeliveryDays } from "@/lib/shipping/config";
import type { Product, ProductImage, ProductOrigin, ProductSpecification } from "@/lib/types/catalog";

const DEFAULT_GRADIENT = "from-zinc-800 via-zinc-700 to-zinc-900";
const DEFAULT_EMOJI = "🛍️";

function parseApiStockQuantity(value: string | number | null | undefined): number | undefined {
  if (value === null || value === undefined || value === "") {
    return undefined;
  }

  const parsed = typeof value === "number" ? value : Number.parseFloat(String(value));
  if (!Number.isFinite(parsed)) {
    return undefined;
  }

  return Math.max(0, Math.floor(parsed));
}

function resolveVariantStockFromApi(
  variants: ApiCatalogProductVariant[] | null | undefined,
): number | undefined {
  if (!variants?.length) {
    return undefined;
  }

  const stocks = variants
    .map(
      (variant) =>
        parseApiStockQuantity(variant.stock) ??
        parseApiStockQuantity(variant.inventory?.available_quantity),
    )
    .filter((value): value is number => value !== undefined);

  if (stocks.length === 0) {
    return undefined;
  }

  return stocks.reduce((total, value) => total + value, 0);
}

export function resolveApiProductStock(input: ApiCatalogStockSource): number {
  const direct =
    parseApiStockQuantity(input.stock) ??
    parseApiStockQuantity(input.quantity_available) ??
    parseApiStockQuantity(input.available_quantity) ??
    parseApiStockQuantity(input.inventory?.available_quantity);

  if (direct !== undefined) {
    return direct;
  }

  return resolveVariantStockFromApi(input.variants) ?? 0;
}

function apiIdToNumericId(id: string): number {
  let hash = 0;

  for (let index = 0; index < id.length; index += 1) {
    hash = (hash * 31 + id.charCodeAt(index)) >>> 0;
  }

  return hash || 1;
}

function parseMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === "") {
    return 0;
  }

  const parsed = typeof value === "number" ? value : Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function parseOptionalMoney(value: string | number | null | undefined): number | undefined {
  if (value === null || value === undefined || value === "") {
    return undefined;
  }

  const parsed = parseMoney(value);
  return parsed > 0 ? parsed : undefined;
}

function mapApiImage(
  image: ApiCatalogImage | null | undefined,
  productName: string,
  index = 0,
): ProductImage | undefined {
  const rawSrc = image?.url?.trim() || image?.path?.trim();

  if (!rawSrc || !image?.id) {
    return undefined;
  }

  return {
    id: apiIdToNumericId(image.id),
    emoji: DEFAULT_EMOJI,
    gradient: DEFAULT_GRADIENT,
    alt: image.alt_text?.trim() || `${productName} image ${index + 1}`,
    url: rawSrc,
    path: image.path?.trim() || undefined,
  };
}

function inferOrigin(input: {
  commerceChannelCode?: string | null;
  commerceSourceLabel?: string | null;
  requiresChinaShipping?: boolean;
  shippingAir?: number;
  shippingSea?: number;
}): ProductOrigin {
  const code = input.commerceChannelCode?.toUpperCase();
  if (code === "TZ_LOCAL") return "tz";
  if (code === "CHINA_IMPORT") return "china";

  const label = input.commerceSourceLabel?.toLowerCase() ?? "";
  if (label.includes("tanzania")) return "tz";
  if (label.includes("china")) return "china";

  if (input.requiresChinaShipping === true) {
    return "china";
  }

  if (input.requiresChinaShipping === false) {
    return "tz";
  }

  if (input.shippingAir || input.shippingSea) {
    return "china";
  }

  return "tz";
}

export function mapApiProductCardToCatalogProduct(product: ApiCatalogProductCard): Product {
  const price = parseMoney(product.price);
  const oldPrice = parseOptionalMoney(product.compare_at_price) ?? 0;
  const stock = resolveApiProductStock(product);
  const primaryImage = mapApiImage(product.primary_image, product.name);
  const badgeLabel = product.is_featured ? "Featured" : "";
  const airCost = parseOptionalMoney(product.shipping_prices?.air);
  const seaCost = parseOptionalMoney(product.shipping_prices?.sea);
  const shippingOptions = [
    ...(airCost ? [{ type: "air" as const, price: airCost }] : []),
    ...(seaCost ? [{ type: "sea" as const, price: seaCost }] : []),
  ];

  return {
    id: apiIdToNumericId(product.id),
    catalogProductId: product.id,
    slug: product.slug,
    name: product.name,
    description: product.short_description?.trim() || product.name,
    shortDescription: product.short_description?.trim() || undefined,
    price,
    oldPrice,
    rating: product.average_rating ?? 0,
    reviews: product.review_count ?? 0,
    badge: badgeLabel,
    badges: resolveProductBadges(badgeLabel, stock),
    trustBadges: product.is_featured ? ["Premium"] : [],
    origin: inferOrigin({
      commerceChannelCode: product.commerce_channel_code,
      commerceSourceLabel: product.commerce_source_label,
      requiresChinaShipping: product.requires_china_shipping,
      shippingAir: airCost,
      shippingSea: seaCost,
    }),
    brand: product.brand?.name,
    brandSlug: product.brand?.slug,
    gradient: DEFAULT_GRADIENT,
    emoji: DEFAULT_EMOJI,
    categorySlug: product.category?.slug ?? "uncategorized",
    stock,
    airCost,
    seaCost,
    shippingOptions: shippingOptions.length > 0 ? shippingOptions : undefined,
    airDeliveryDays: airCost
      ? getDefaultFlatShippingDeliveryDays("air_freight")
      : undefined,
    seaDeliveryDays: seaCost
      ? getDefaultFlatShippingDeliveryDays("sea_freight")
      : undefined,
    primary_image: primaryImage,
    images: primaryImage ? [primaryImage] : [],
    image: primaryImage?.url ?? primaryImage?.path,
    features: [],
    specifications: [] as ProductSpecification[],
    customerReviews: [],
    featured: product.is_featured,
    status: "active",
  };
}

export function mapApiProductDetailToCatalogProduct(product: ApiCatalogProductDetail): Product {
  const card = mapApiProductCardToCatalogProduct(product);
  const stock = resolveApiProductStock(product);
  const images = (product.images ?? [])
    .map((image, index) => mapApiImage(image, product.name, index))
    .filter((image): image is ProductImage => Boolean(image));

  const airCost = parseOptionalMoney(product.shipping_prices?.air) ?? card.airCost;
  const seaCost = parseOptionalMoney(product.shipping_prices?.sea) ?? card.seaCost;
  const weightKg = parseOptionalMoney(product.weight);
  const shippingOptions = [
    ...(airCost ? [{ type: "air" as const, price: airCost }] : []),
    ...(seaCost ? [{ type: "sea" as const, price: seaCost }] : []),
  ];

  return {
    ...card,
    stock,
    badges: resolveProductBadges(card.badge, stock),
    description: product.description?.trim() || card.description,
    shortDescription: product.short_description?.trim() || card.shortDescription,
    fullDescription: product.description?.trim() || undefined,
    sku: product.variants?.[0]?.sku?.trim() || undefined,
    weightKg,
    origin: inferOrigin({
      commerceChannelCode: product.commerce_channel?.code ?? product.commerce_channel_code,
      commerceSourceLabel: product.commerce_source_label,
      requiresChinaShipping: product.requires_china_shipping,
      shippingAir: airCost,
      shippingSea: seaCost,
    }),
    airCost,
    seaCost,
    shippingOptions: shippingOptions.length > 0 ? shippingOptions : undefined,
    airDeliveryDays: airCost
      ? getDefaultFlatShippingDeliveryDays("air_freight")
      : undefined,
    seaDeliveryDays: seaCost
      ? getDefaultFlatShippingDeliveryDays("sea_freight")
      : undefined,
    primary_image: images[0] ?? card.primary_image,
    images: images.length > 0 ? images : card.images,
    image: images[0]?.url ?? images[0]?.path ?? card.image,
    specifications: product.dimensions
      ? [{ label: "Dimensions", value: product.dimensions }]
      : [],
  };
}
