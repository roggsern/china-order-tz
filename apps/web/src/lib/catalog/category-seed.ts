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
    name: "Beauty & Cosmetics",
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
