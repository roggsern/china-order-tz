import type { Category } from "@/lib/types/catalog";

export const categories: Category[] = [
  {
    slug: "womens-fashion",
    name: "Women's Fashion",
    description: "Trending styles shipped from Guangzhou",
    gradient: "from-rose-400 via-pink-500 to-fuchsia-600",
    icon: "👗",
  },
  {
    slug: "mens-fashion",
    name: "Men's Fashion",
    description: "Premium apparel at factory prices",
    gradient: "from-slate-600 via-zinc-700 to-neutral-900",
    icon: "👔",
  },
  {
    slug: "electronics",
    name: "Electronics",
    description: "Gadgets, accessories & smart devices",
    gradient: "from-blue-500 via-indigo-600 to-violet-700",
    icon: "📱",
  },
  {
    slug: "beauty",
    name: "Beauty",
    description: "Skincare, makeup & wellness picks",
    gradient: "from-amber-300 via-orange-400 to-rose-400",
    icon: "💄",
  },
  {
    slug: "furniture",
    name: "Furniture",
    description: "Modern home & office furniture direct from factories",
    gradient: "from-emerald-500 via-teal-600 to-cyan-700",
    icon: "🛋️",
  },
  {
    slug: "building-materials",
    name: "Building Materials",
    description: "Tiles, fixtures & construction supplies",
    gradient: "from-stone-500 via-amber-700 to-yellow-800",
    icon: "🏗️",
  },
  {
    slug: "home-kitchen",
    name: "Home & Kitchen",
    description: "Appliances, cookware & home essentials",
    gradient: "from-orange-400 via-red-500 to-rose-600",
    icon: "🏠",
  },
  {
    slug: "kids-baby",
    name: "Kids & Baby",
    description: "Clothing, toys & nursery essentials",
    gradient: "from-sky-400 via-blue-500 to-indigo-600",
    icon: "🧸",
  },
];

export function getSubcategories(slug: string): readonly string[] {
  const map: Record<string, readonly string[]> = {
    "womens-fashion": ["Dresses", "Tops & Blouses", "Shoes", "Bags", "Activewear", "Accessories"],
    "mens-fashion": ["Shirts", "Pants & Jeans", "Suits", "Shoes", "Watches", "Sportswear"],
    electronics: ["Smartphones", "Laptops", "Audio", "Smart Home", "Cameras", "Accessories"],
    beauty: ["Skincare", "Makeup", "Hair Care", "Fragrances", "Tools", "Wellness"],
    furniture: ["Living Room", "Bedroom", "Office", "Outdoor", "Storage", "Lighting"],
    "building-materials": ["Tiles", "Doors & Windows", "Plumbing", "Electrical", "Paint", "Tools"],
    "home-kitchen": ["Cookware", "Small Appliances", "Bedding", "Decor", "Storage", "Tableware"],
    "kids-baby": ["Clothing", "Toys", "Strollers", "Feeding", "Nursery", "School Supplies"],
  };
  return map[slug] ?? [];
}

export function getFeaturedForCategory(slug: string): string {
  const map: Record<string, string> = {
    "womens-fashion": "Summer Collection 2026",
    "mens-fashion": "Business Essentials",
    electronics: "Smart Gadgets Sale",
    beauty: "K-Beauty Favorites",
    furniture: "Modern Living Sets",
    "building-materials": "Bulk Tile Deals",
    "home-kitchen": "Kitchen Upgrade Picks",
    "kids-baby": "Back to School",
  };
  return map[slug] ?? "Featured Picks";
}

export const megaMenuCategories = categories.map((category) => ({
  ...category,
  subcategories: getSubcategories(category.slug),
  featured: getFeaturedForCategory(category.slug),
}));

export function getCategoryBySlug(slug: string): Category | undefined {
  return categories.find((c) => c.slug === slug);
}
