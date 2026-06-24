export const navLinks = [
  { label: "Home", href: "#home" },
  { label: "How It Works", href: "#how-it-works" },
  { label: "Order From China", href: "#order-from-china" },
  { label: "About", href: "#about" },
  { label: "Contact", href: "#contact" },
] as const;

export const categories = [
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
] as const;

export const megaMenuCategories = categories.map((category) => ({
  ...category,
  subcategories: getSubcategories(category.slug),
  featured: getFeaturedForCategory(category.slug),
}));

function getSubcategories(slug: string): readonly string[] {
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

function getFeaturedForCategory(slug: string): string {
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

export const howItWorksSteps = [
  {
    step: 1,
    title: "Paste your link",
    description:
      "Copy a product URL from Alibaba, 1688, Taobao, or Temu and paste it into our order form.",
    detail: "Alibaba · 1688 · Taobao · Temu",
    icon: "link",
  },
  {
    step: 2,
    title: "Receive quotation",
    description:
      "Our sourcing team reviews your request and sends a detailed quote in TZS within 24–48 hours.",
    detail: "Itemized pricing · Shipping estimate",
    icon: "quote",
  },
  {
    step: 3,
    title: "Pay and track shipment",
    description:
      "Accept your quote, pay securely via M-Pesa or card, and track your order from China to Tanzania.",
    detail: "M-Pesa · Cards · Real-time tracking",
    icon: "track",
  },
] as const;

export const chinaOrderActions = [
  {
    id: "upload",
    title: "Upload Product Image",
    description: "Have a photo or screenshot? Upload it and we'll find matching suppliers for you.",
    cta: "Upload Image",
    icon: "upload",
  },
  {
    id: "link",
    title: "Paste Product Link",
    description: "Paste any Alibaba, 1688, Taobao, or Temu product URL to start your import request.",
    cta: "Paste Link",
    icon: "link",
  },
  {
    id: "quote",
    title: "Request Quotation",
    description: "Submit your requirements and receive a professional, itemized quote in Tanzanian Shillings.",
    cta: "Get Quote",
    icon: "quote",
  },
] as const;

export const supportedPlatforms = ["Alibaba", "1688", "Taobao", "Temu"] as const;

export const featuredProducts = [
  {
    id: 1,
    name: "Pro Wireless Earbuds",
    price: 89000,
    oldPrice: 145000,
    rating: 4.8,
    reviews: 2341,
    badge: "Hot Deal",
    gradient: "from-zinc-900 to-zinc-700",
    emoji: "🎧",
  },
  {
    id: 2,
    name: "Smart Watch Ultra",
    price: 156000,
    oldPrice: 220000,
    rating: 4.9,
    reviews: 1876,
    badge: "Best Seller",
    gradient: "from-slate-800 to-blue-900",
    emoji: "⌚",
  },
  {
    id: 3,
    name: "Floral Summer Dress",
    price: 42000,
    oldPrice: 78000,
    rating: 4.7,
    reviews: 956,
    badge: "-46%",
    gradient: "from-rose-300 to-pink-500",
    emoji: "👗",
  },
  {
    id: 4,
    name: "Urban Runner Sneakers",
    price: 68000,
    oldPrice: 115000,
    rating: 4.6,
    reviews: 1432,
    badge: "New",
    gradient: "from-orange-400 to-red-500",
    emoji: "👟",
  },
  {
    id: 5,
    name: "18\" LED Ring Light",
    price: 54000,
    oldPrice: 92000,
    rating: 4.5,
    reviews: 678,
    badge: "Creator Pick",
    gradient: "from-violet-500 to-purple-800",
    emoji: "💡",
  },
  {
    id: 6,
    name: "K-Beauty Skincare Set",
    price: 76000,
    oldPrice: 128000,
    rating: 4.8,
    reviews: 2103,
    badge: "Top Rated",
    gradient: "from-teal-300 to-emerald-500",
    emoji: "✨",
  },
  {
    id: 7,
    name: "Porcelain Floor Tiles (20pc)",
    price: 185000,
    oldPrice: 260000,
    rating: 4.7,
    reviews: 412,
    badge: "Bulk Save",
    gradient: "from-amber-600 to-stone-800",
    emoji: "🧱",
  },
  {
    id: 8,
    name: "20000mAh Power Bank",
    price: 38000,
    oldPrice: 65000,
    rating: 4.6,
    reviews: 3201,
    badge: "Flash Sale",
    gradient: "from-cyan-500 to-blue-700",
    emoji: "🔋",
  },
] as const;

export const whyChooseUs = [
  {
    title: "Fast Shipping",
    description: "Air & sea freight options from China to Tanzania with real-time tracking.",
    icon: "shipping",
  },
  {
    title: "Trusted Suppliers",
    description: "Vetted factories and verified sellers — quality you can count on.",
    icon: "shield",
  },
  {
    title: "Affordable Prices",
    description: "Direct-from-factory pricing with no middleman markups.",
    icon: "tag",
  },
  {
    title: "Secure Payments",
    description: "Encrypted checkout with M-Pesa, cards, and bank transfer support.",
    icon: "lock",
  },
] as const;

export const footerLinks = {
  shop: [
    { label: "All Categories", href: "#categories" },
    { label: "Featured Deals", href: "#products" },
    { label: "New Arrivals", href: "#products" },
    { label: "Bulk Orders", href: "#contact" },
  ],
  company: [
    { label: "About Us", href: "#about" },
    { label: "How It Works", href: "#how-it-works" },
    { label: "Order From China", href: "#order-from-china" },
    { label: "Contact", href: "#contact" },
  ],
  support: [
    { label: "Help Center", href: "#contact" },
    { label: "Track Order", href: "#contact" },
    { label: "Returns", href: "#contact" },
    { label: "Privacy Policy", href: "#contact" },
  ],
} as const;

export function formatPrice(amount: number): string {
  return new Intl.NumberFormat("en-TZ", {
    style: "currency",
    currency: "TZS",
    maximumFractionDigits: 0,
  }).format(amount);
}
