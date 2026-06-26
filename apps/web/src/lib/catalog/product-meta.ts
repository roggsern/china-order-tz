import type {
  CustomerReview,
  ProductOrigin,
  ProductSpecification,
  TrustBadgeType,
} from "@/lib/types/catalog";

type ProductMeta = {
  origin?: ProductOrigin;
  brand?: string;
  brandSlug?: string;
  trustBadges?: TrustBadgeType[];
  specifications?: ProductSpecification[];
  customerReviews?: CustomerReview[];
};

export const productMetaBySlug: Record<string, ProductMeta> = {
  "pro-wireless-earbuds": {
    trustBadges: ["Verified Supplier", "Fast Shipping", "Premium"],
    specifications: [
      { label: "Battery Life", value: "36 hours (with case)" },
      { label: "Water Resistance", value: "IPX5" },
      { label: "Connectivity", value: "Bluetooth 5.3" },
      { label: "Noise Cancellation", value: "Active (ANC)" },
    ],
  },
  "smart-watch-ultra": {
    trustBadges: ["Verified Supplier", "Best Seller", "Fast Shipping"],
    specifications: [
      { label: "Display", value: "1.9\" AMOLED" },
      { label: "Battery", value: "Up to 7 days" },
      { label: "Water Resistance", value: "50m" },
      { label: "GPS", value: "Built-in" },
    ],
  },
  "floral-summer-dress": {
    origin: "tz",
    brand: "Zion Mode",
    brandSlug: "zion-mode",
    trustBadges: ["Premium", "Fast Shipping"],
    specifications: [
      { label: "Material", value: "Lightweight Chiffon" },
      { label: "Fit", value: "Regular with waist tie" },
      { label: "Origin", value: "Made in Tanzania" },
    ],
  },
  "urban-runner-sneakers": {
    trustBadges: ["Verified Supplier", "Trending"],
    specifications: [
      { label: "Upper", value: "Breathable mesh" },
      { label: "Sole", value: "Cushioned anti-slip" },
      { label: "Sizes", value: "EU 39–45" },
    ],
  },
  "designer-handbag": {
    origin: "tz",
    brand: "Zion Mode",
    brandSlug: "zion-mode",
    trustBadges: ["Premium", "Trending"],
    specifications: [
      { label: "Material", value: "PU Leather" },
      { label: "Strap", value: "Adjustable crossbody" },
      { label: "Origin", value: "Made in Tanzania" },
    ],
  },
  "silk-scarf-collection": {
    origin: "tz",
    brand: "Zion Mode",
    brandSlug: "zion-mode",
    trustBadges: ["Premium", "Best Seller"],
    specifications: [
      { label: "Material", value: "Silk blend" },
      { label: "Pack", value: "3 unique prints" },
      { label: "Origin", value: "Made in Tanzania" },
    ],
  },
  "matte-lipstick-set": {
    origin: "tz",
    brand: "Peachy Lingerie",
    brandSlug: "peachy-lingerie",
    trustBadges: ["Premium", "Verified Supplier"],
    specifications: [
      { label: "Shades", value: "6 matte colors" },
      { label: "Formula", value: "Vitamin E, paraben-free" },
      { label: "Origin", value: "Made in Tanzania" },
    ],
  },
  "cotton-bedsheet-set": {
    origin: "tz",
    brand: "Zion Mode",
    brandSlug: "zion-mode",
    trustBadges: ["Premium", "Fast Shipping"],
    specifications: [
      { label: "Thread Count", value: "400 TC cotton" },
      { label: "Set Includes", value: "Fitted sheet, flat sheet, 2 pillowcases" },
      { label: "Origin", value: "Made in Tanzania" },
    ],
  },
  "modern-sofa-set": {
    trustBadges: ["Verified Supplier", "Premium"],
    specifications: [
      { label: "Frame", value: "Solid wood" },
      { label: "Seating", value: "L-shaped 3-seater" },
      { label: "Fabric", value: "Stain-resistant" },
    ],
  },
  "k-beauty-skincare-set": {
    trustBadges: ["Verified Supplier", "Best Seller", "Premium"],
    specifications: [
      { label: "Pieces", value: "5-step routine" },
      { label: "Key Ingredients", value: "Hyaluronic acid, niacinamide" },
      { label: "Skin Type", value: "All skin types" },
    ],
  },
};

export function getDefaultSpecifications(
  features: string[],
  origin: ProductOrigin,
): ProductSpecification[] {
  const specs: ProductSpecification[] = features.slice(0, 4).map((feature) => {
    const [label, ...rest] = feature.split(":");
    if (rest.length > 0) {
      return { label: label.trim(), value: rest.join(":").trim() };
    }
    return { label: "Feature", value: feature };
  });

  specs.push({
    label: "Origin",
    value: origin === "tz" ? "Buy From TZ — Local" : "Imported from China",
  });

  return specs;
}

export function getDefaultReviews(
  productName: string,
  rating: number,
): CustomerReview[] {
  const samples = [
    {
      title: "Excellent quality",
      comment: `Very happy with ${productName}. Exactly as described and arrived well packaged.`,
    },
    {
      title: "Great value for money",
      comment: "Premium feel at a fair price. Would definitely recommend to friends and family.",
    },
    {
      title: "Fast and reliable",
      comment: "Smooth ordering experience. Product quality exceeded my expectations.",
    },
  ];

  return samples.map((sample, index) => ({
    id: index + 1,
    author: ["Amina K.", "James M.", "Fatima S."][index],
    rating: Math.min(5, Math.max(4, Math.round(rating))),
    date: ["Jan 2026", "Feb 2026", "Mar 2026"][index],
    title: sample.title,
    comment: sample.comment,
    verified: true,
  }));
}

export function getDefaultTrustBadges(origin: ProductOrigin, rating: number): TrustBadgeType[] {
  const badges: TrustBadgeType[] = ["Verified Supplier"];
  if (origin === "china") badges.push("Fast Shipping");
  if (rating >= 4.7) badges.push("Premium");
  if (rating >= 4.8) badges.push("Best Seller");
  return badges.slice(0, 3);
}
