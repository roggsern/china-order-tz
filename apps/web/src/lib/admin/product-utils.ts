import type { Product, ProductFormData, ProductValidationErrors } from "@/lib/types/catalog";
import {
  getBrandDisplayLabel,
  getBrandSubcategories,
  getDefaultBuyFromDarBrand,
  isValidBrandSubcategory,
} from "@/lib/catalog/brands";
import {
  getProductPrimaryImage,
  normalizeProductImagesForSave,
} from "@/lib/catalog/product-images";
import { resolveProductBadges } from "@/lib/catalog/badges";
import {
  getDefaultReviews,
  getDefaultSpecifications,
  getDefaultTrustBadges,
} from "@/lib/catalog/product-meta";
import { normalizeDeliveryDays } from "@/lib/catalog/delivery";
import { isLocalProductType, productTypeToOrigin, resolveProductType } from "@/lib/catalog/product-type";
import { normalizeProductVariants } from "@/lib/catalog/variants";
import { slugify } from "@/lib/catalog/utils";

export function enrichProductForAdmin(product: Product): Product {
  const createdAt =
    product.createdAt ??
    new Date(Date.now() - product.id * 86_400_000).toISOString();

  const images = normalizeProductImagesForSave(product.images ?? []);

  return {
    ...product,
    type: resolveProductType(product),
    images,
    image: product.image ?? images.find((img) => img.url)?.url,
    shortDescription: product.shortDescription ?? product.description.slice(0, 160),
    fullDescription: product.fullDescription ?? product.description,
    sku: product.sku ?? `SKU-${String(product.id).padStart(5, "0")}`,
    createdAt,
    airCost: product.airCost,
    seaCost: product.origin === "china" ? product.seaCost : undefined,
    airDeliveryDays: normalizeDeliveryDays(product.airDeliveryDays),
    seaDeliveryDays:
      product.origin === "china" ? normalizeDeliveryDays(product.seaDeliveryDays) : undefined,
    thumbnailImageId: product.thumbnailImageId ?? product.images[0]?.id ?? undefined,
    bestSeller: product.bestSeller ?? product.badge.toLowerCase().includes("best"),
    trending: product.trending ?? product.badge.toLowerCase().includes("trend"),
    newArrival: product.newArrival ?? product.badge.toLowerCase().includes("new"),
    discountPercent:
      product.discountPercent ??
      (product.oldPrice > product.price
        ? Math.floor(((product.oldPrice - product.price) / product.oldPrice) * 100)
        : 0),
    variants: normalizeProductVariants(product.variants),
  };
}

export function resolvePricing(data: ProductFormData): { price: number; oldPrice: number } {
  return {
    price: data.price,
    oldPrice: data.oldPrice > data.price ? data.oldPrice : 0,
  };
}

export function formDataToProduct(data: ProductFormData, id: number, createdAt?: string): Product {
  const { price, oldPrice } = resolvePricing(data);
  const productType = data.type;
  const origin = productTypeToOrigin(productType);
  const isLocal = isLocalProductType(productType);
  const normalizedImages = normalizeProductImagesForSave(data.images);
  const images =
    normalizedImages.length > 0
      ? normalizedImages
      : [
          {
            id: 1,
            emoji: data.emoji,
            gradient: data.gradient,
            alt: data.name,
          },
        ];

  const thumbnailImageId = data.thumbnailImageId ?? images[0]?.id;
  const orderedImages =
    thumbnailImageId != null
      ? [
          ...images.filter((img) => img.id === thumbnailImageId),
          ...images.filter((img) => img.id !== thumbnailImageId),
        ]
      : images;

  const features = data.features
    .split("\n")
    .map((feature) => feature.trim())
    .filter(Boolean);

  const description = data.fullDescription.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim()
    || data.shortDescription
    || data.description;

  return {
    id,
    slug: data.slug || slugify(data.name),
    name: data.name,
    description,
    shortDescription: data.shortDescription,
    fullDescription: data.fullDescription,
    price,
    oldPrice,
    rating: data.rating,
    reviews: data.reviews,
    badge: data.badge,
    badges: resolveProductBadges(data.badge, data.stock),
    trustBadges: getDefaultTrustBadges(origin, data.rating),
    type: productType,
    origin,
    brand: isLocal ? getBrandDisplayLabel(data.brandSlug) ?? data.brand : undefined,
    brandSlug: isLocal ? data.brandSlug : undefined,
    subcategorySlug: isLocal ? data.subcategorySlug : data.subcategorySlug || undefined,
    gradient: data.gradient,
    emoji: data.emoji,
    categorySlug: isLocal ? data.brandSlug : data.categorySlug,
    stock: data.stock,
    weightKg: data.weightKg ?? undefined,
    sku: data.sku,
    airCost: data.airCost > 0 ? data.airCost : undefined,
    seaCost: productType === "china" && data.seaCost > 0 ? data.seaCost : undefined,
    airDeliveryDays: normalizeDeliveryDays(data.airDeliveryDays),
    seaDeliveryDays:
      productType === "china" ? normalizeDeliveryDays(data.seaDeliveryDays) : undefined,
    discountPercent: data.discountPercent,
    images: orderedImages,
    image: orderedImages.find((img) => img.url)?.url,
    thumbnailImageId: thumbnailImageId ?? undefined,
    features,
    specifications: getDefaultSpecifications(features, origin),
    customerReviews: getDefaultReviews(data.name, data.rating),
    featured: data.featured,
    bestSeller: data.bestSeller,
    trending: data.trending,
    newArrival: data.newArrival,
    status: data.status,
    createdAt: createdAt ?? new Date().toISOString(),
    variants: normalizeProductVariants(data.variants),
  };
}

export function productToFormData(product: Product): ProductFormData {
  const enriched = enrichProductForAdmin(product);
  const productType = resolveProductType(enriched);
  const isLocal = isLocalProductType(productType);
  const defaultLocalBrand = getDefaultBuyFromDarBrand();
  const localBrandSlug = isLocal ? enriched.brandSlug ?? defaultLocalBrand.slug : "";
  const localSubcategories = isLocal ? getBrandSubcategories(localBrandSlug) : [];
  const localSubcategorySlug = isLocal
    ? localSubcategories.find((item) => item.slug === enriched.subcategorySlug)?.slug ??
      localSubcategories[0]?.slug ??
      ""
    : enriched.subcategorySlug ?? "";

  return {
    name: enriched.name,
    slug: enriched.slug,
    shortDescription: enriched.shortDescription ?? "",
    description: enriched.description,
    fullDescription: enriched.fullDescription ?? enriched.description,
    price: enriched.price,
    oldPrice: enriched.oldPrice > enriched.price ? enriched.oldPrice : 0,
    discountPercent: enriched.discountPercent ?? 0,
    rating: enriched.rating,
    reviews: enriched.reviews,
    badge: enriched.badge,
    gradient: enriched.gradient,
    emoji: enriched.emoji,
    type: productType,
    origin: productTypeToOrigin(productType),
    brandSlug: isLocal ? localBrandSlug : "",
    brand: isLocal
      ? enriched.brand ?? getBrandDisplayLabel(localBrandSlug) ?? defaultLocalBrand.name
      : "",
    categorySlug: isLocal ? localBrandSlug : enriched.categorySlug,
    subcategorySlug: localSubcategorySlug,
    stock: enriched.stock,
    sku: enriched.sku ?? "",
    weightKg: enriched.weightKg ?? null,
    airCost: enriched.airCost ?? 0,
    seaCost: enriched.seaCost ?? 0,
    airDeliveryDays: enriched.airDeliveryDays ?? "",
    seaDeliveryDays: enriched.seaDeliveryDays ?? "",
    features: enriched.features.join("\n"),
    featured: enriched.featured,
    bestSeller: enriched.bestSeller ?? false,
    trending: enriched.trending ?? false,
    newArrival: enriched.newArrival ?? false,
    status: enriched.status,
    images: enriched.images,
    thumbnailImageId: enriched.thumbnailImageId ?? enriched.images[0]?.id ?? null,
    variants: normalizeProductVariants(enriched.variants) ?? {},
  };
}

export function validateProductForm(data: ProductFormData): ProductValidationErrors {
  const errors: ProductValidationErrors = {};
  const isLocal = isLocalProductType(data.type);

  if (!data.name.trim()) errors.name = "Product name is required.";
  if (!data.type) errors.type = "Product type is required.";
  if (!data.price || data.price <= 0) errors.price = "Price must be greater than zero.";

  if (isLocal) {
    if (!data.brandSlug) {
      errors.brandSlug = "Brand is required for Buy from Dar products.";
    }
    if (!data.subcategorySlug) {
      errors.subcategorySlug = "Subcategory is required for Buy from Dar products.";
    } else if (!isValidBrandSubcategory(data.brandSlug, data.subcategorySlug)) {
      errors.subcategorySlug = "Choose a subcategory that matches the selected brand.";
    }
  } else {
    if (!data.categorySlug) errors.categorySlug = "Category is required.";
    if (data.brandSlug) {
      errors.brandSlug = undefined;
    }
  }

  if (data.type === "china") {
    if (!data.airCost || data.airCost <= 0) errors.airCost = "Air cost is required for China products.";
    if (!data.seaCost || data.seaCost <= 0) errors.seaCost = "Sea cost is required for China products.";
    if (!data.airDeliveryDays.trim()) {
      errors.airDeliveryDays = "Air delivery days are required for China products.";
    }
    if (!data.seaDeliveryDays.trim()) {
      errors.seaDeliveryDays = "Sea delivery days are required for China products.";
    }
  } else if (!data.airDeliveryDays.trim()) {
    errors.airDeliveryDays = "Local delivery days are optional but recommended for Dar products.";
  }

  return errors;
}

export function getProductThumbnail(product: Product) {
  const thumbnailId = product.thumbnailImageId ?? product.images[0]?.id;
  const match = product.images.find((image) => image.id === thumbnailId);
  if (match) return match;
  if (product.images[0]) return product.images[0];
  return getProductPrimaryImage(product);
}

export function formatAdminDate(iso?: string): string {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(new Date(iso));
}
