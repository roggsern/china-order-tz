import type { Product, ProductStatus, SortOption } from "@/lib/types/catalog";

type ProductSeed = Omit<Product, "featured" | "status">;

const productSeeds: ProductSeed[] = [
  {
    id: 1,
    slug: "pro-wireless-earbuds",
    name: "Pro Wireless Earbuds",
    description:
      "Premium true wireless earbuds with active noise cancellation, 36-hour battery life with charging case, and IPX5 water resistance. Crystal-clear calls and deep bass for music lovers on the go.",
    price: 89000,
    oldPrice: 145000,
    rating: 4.8,
    reviews: 2341,
    badge: "Hot Deal",
    gradient: "from-zinc-900 to-zinc-700",
    emoji: "🎧",
    categorySlug: "electronics",
    stock: 48,
    images: [
      { id: 1, emoji: "🎧", gradient: "from-zinc-900 to-zinc-700", alt: "Pro Wireless Earbuds front view" },
      { id: 2, emoji: "🔋", gradient: "from-zinc-800 to-zinc-600", alt: "Charging case" },
      { id: 3, emoji: "📦", gradient: "from-zinc-700 to-zinc-500", alt: "Package contents" },
    ],
    features: ["Active Noise Cancellation", "36hr Battery Life", "IPX5 Water Resistant", "Touch Controls"],
  },
  {
    id: 2,
    slug: "smart-watch-ultra",
    name: "Smart Watch Ultra",
    description:
      "Advanced fitness smartwatch with heart rate monitoring, GPS tracking, sleep analysis, and 7-day battery. Compatible with iOS and Android. Rugged titanium design built for adventure.",
    price: 156000,
    oldPrice: 220000,
    rating: 4.9,
    reviews: 1876,
    badge: "Best Seller",
    gradient: "from-slate-800 to-blue-900",
    emoji: "⌚",
    categorySlug: "electronics",
    stock: 32,
    images: [
      { id: 1, emoji: "⌚", gradient: "from-slate-800 to-blue-900", alt: "Smart Watch Ultra" },
      { id: 2, emoji: "🏃", gradient: "from-blue-900 to-indigo-800", alt: "Fitness tracking" },
      { id: 3, emoji: "💪", gradient: "from-indigo-800 to-violet-900", alt: "Health metrics" },
    ],
    features: ["Heart Rate Monitor", "GPS Tracking", "7-Day Battery", "Water Resistant 50m"],
  },
  {
    id: 3,
    slug: "floral-summer-dress",
    name: "Floral Summer Dress",
    description:
      "Lightweight chiffon midi dress with vibrant floral print. Perfect for beach outings, garden parties, and casual summer events. Available in multiple sizes with adjustable waist tie.",
    price: 42000,
    oldPrice: 78000,
    rating: 4.7,
    reviews: 956,
    badge: "-46%",
    gradient: "from-rose-300 to-pink-500",
    emoji: "👗",
    categorySlug: "womens-fashion",
    stock: 65,
    images: [
      { id: 1, emoji: "👗", gradient: "from-rose-300 to-pink-500", alt: "Floral Summer Dress" },
      { id: 2, emoji: "🌸", gradient: "from-pink-400 to-rose-500", alt: "Floral detail" },
      { id: 3, emoji: "👠", gradient: "from-rose-400 to-fuchsia-500", alt: "Styled outfit" },
    ],
    features: ["Lightweight Chiffon", "Adjustable Waist Tie", "Machine Washable", "Multiple Sizes"],
  },
  {
    id: 4,
    slug: "urban-runner-sneakers",
    name: "Urban Runner Sneakers",
    description:
      "Breathable mesh running shoes with cushioned sole and anti-slip grip. Lightweight design for all-day comfort. Trendy street-style look suitable for sports and casual wear.",
    price: 68000,
    oldPrice: 115000,
    rating: 4.6,
    reviews: 1432,
    badge: "New",
    gradient: "from-orange-400 to-red-500",
    emoji: "👟",
    categorySlug: "mens-fashion",
    stock: 54,
    images: [
      { id: 1, emoji: "👟", gradient: "from-orange-400 to-red-500", alt: "Urban Runner Sneakers" },
      { id: 2, emoji: "🏃", gradient: "from-red-400 to-orange-500", alt: "Side profile" },
      { id: 3, emoji: "👣", gradient: "from-orange-500 to-amber-600", alt: "Sole detail" },
    ],
    features: ["Breathable Mesh", "Cushioned Sole", "Anti-Slip Grip", "Lightweight Design"],
  },
  {
    id: 5,
    slug: "led-ring-light-18",
    name: '18" LED Ring Light',
    description:
      "Professional 18-inch ring light with adjustable color temperature (3200K–5600K) and brightness. Includes tripod stand and phone holder. Ideal for content creators, live streaming, and photography.",
    price: 54000,
    oldPrice: 92000,
    rating: 4.5,
    reviews: 678,
    badge: "Creator Pick",
    gradient: "from-violet-500 to-purple-800",
    emoji: "💡",
    categorySlug: "electronics",
    stock: 28,
    images: [
      { id: 1, emoji: "💡", gradient: "from-violet-500 to-purple-800", alt: "LED Ring Light" },
      { id: 2, emoji: "📸", gradient: "from-purple-600 to-violet-700", alt: "With tripod" },
      { id: 3, emoji: "🎬", gradient: "from-violet-600 to-indigo-800", alt: "Studio setup" },
    ],
    features: ["Adjustable Color Temp", "Tripod Included", "Phone Holder", "Dimmer Control"],
  },
  {
    id: 6,
    slug: "k-beauty-skincare-set",
    name: "K-Beauty Skincare Set",
    description:
      "Complete 5-piece Korean skincare routine: cleanser, toner, serum, moisturizer, and sunscreen. Formulated with hyaluronic acid and niacinamide for radiant, hydrated skin.",
    price: 76000,
    oldPrice: 128000,
    rating: 4.8,
    reviews: 2103,
    badge: "Top Rated",
    gradient: "from-teal-300 to-emerald-500",
    emoji: "✨",
    categorySlug: "beauty",
    stock: 41,
    images: [
      { id: 1, emoji: "✨", gradient: "from-teal-300 to-emerald-500", alt: "K-Beauty Skincare Set" },
      { id: 2, emoji: "🧴", gradient: "from-emerald-400 to-teal-500", alt: "Product lineup" },
      { id: 3, emoji: "💆", gradient: "from-teal-400 to-cyan-500", alt: "Application" },
    ],
    features: ["5-Piece Routine", "Hyaluronic Acid", "Niacinamide Formula", "All Skin Types"],
  },
  {
    id: 7,
    slug: "porcelain-floor-tiles-20pc",
    name: "Porcelain Floor Tiles (20pc)",
    description:
      "High-grade porcelain floor tiles with marble-effect finish. 600×600mm size, 20 pieces per box covering 7.2 sqm. Scratch-resistant, low water absorption, suitable for indoor and outdoor use.",
    price: 185000,
    oldPrice: 260000,
    rating: 4.7,
    reviews: 412,
    badge: "Bulk Save",
    gradient: "from-amber-600 to-stone-800",
    emoji: "🧱",
    categorySlug: "building-materials",
    stock: 120,
    images: [
      { id: 1, emoji: "🧱", gradient: "from-amber-600 to-stone-800", alt: "Porcelain Floor Tiles" },
      { id: 2, emoji: "🏠", gradient: "from-stone-600 to-amber-700", alt: "Installed floor" },
      { id: 3, emoji: "📐", gradient: "from-amber-700 to-yellow-800", alt: "Tile dimensions" },
    ],
    features: ["Marble Effect", "600×600mm Size", "Scratch Resistant", "Indoor & Outdoor"],
  },
  {
    id: 8,
    slug: "20000mah-power-bank",
    name: "20000mAh Power Bank",
    description:
      "High-capacity 20000mAh portable charger with dual USB-C and USB-A ports. Fast charging support for phones, tablets, and laptops. LED display shows remaining battery percentage.",
    price: 38000,
    oldPrice: 65000,
    rating: 4.6,
    reviews: 3201,
    badge: "Flash Sale",
    gradient: "from-cyan-500 to-blue-700",
    emoji: "🔋",
    categorySlug: "electronics",
    stock: 89,
    images: [
      { id: 1, emoji: "🔋", gradient: "from-cyan-500 to-blue-700", alt: "20000mAh Power Bank" },
      { id: 2, emoji: "⚡", gradient: "from-blue-600 to-cyan-600", alt: "Fast charging ports" },
      { id: 3, emoji: "📱", gradient: "from-cyan-600 to-teal-700", alt: "Charging phone" },
    ],
    features: ["20000mAh Capacity", "Dual USB-C/A Ports", "Fast Charging", "LED Battery Display"],
  },
  {
    id: 9,
    slug: "linen-blend-blazer",
    name: "Linen Blend Blazer",
    description:
      "Tailored men's linen-cotton blend blazer in classic navy. Breathable fabric ideal for Tanzania's climate. Slim fit with two-button closure and functional pockets.",
    price: 95000,
    oldPrice: 160000,
    rating: 4.5,
    reviews: 534,
    badge: "New Arrival",
    gradient: "from-slate-700 to-blue-900",
    emoji: "🧥",
    categorySlug: "mens-fashion",
    stock: 22,
    images: [
      { id: 1, emoji: "🧥", gradient: "from-slate-700 to-blue-900", alt: "Linen Blend Blazer" },
      { id: 2, emoji: "👔", gradient: "from-blue-800 to-slate-800", alt: "Styled with shirt" },
    ],
    features: ["Linen-Cotton Blend", "Slim Fit", "Breathable Fabric", "Functional Pockets"],
  },
  {
    id: 10,
    slug: "designer-handbag",
    name: "Designer Crossbody Handbag",
    description:
      "Elegant PU leather crossbody bag with gold-tone hardware and adjustable strap. Multiple compartments for phone, wallet, and essentials. Timeless design for everyday elegance.",
    price: 58000,
    oldPrice: 98000,
    rating: 4.7,
    reviews: 789,
    badge: "Trending",
    gradient: "from-amber-400 to-orange-600",
    emoji: "👜",
    categorySlug: "womens-fashion",
    stock: 37,
    images: [
      { id: 1, emoji: "👜", gradient: "from-amber-400 to-orange-600", alt: "Designer Crossbody Handbag" },
      { id: 2, emoji: "✨", gradient: "from-orange-500 to-amber-500", alt: "Hardware detail" },
    ],
    features: ["PU Leather", "Adjustable Strap", "Multiple Compartments", "Gold-Tone Hardware"],
  },
  {
    id: 11,
    slug: "modern-sofa-set",
    name: "Modern 3-Seater Sofa Set",
    description:
      "Contemporary L-shaped sofa with high-density foam cushions and stain-resistant fabric. Solid wood frame with reinforced legs. Includes matching throw pillows.",
    price: 890000,
    oldPrice: 1250000,
    rating: 4.6,
    reviews: 156,
    badge: "Premium",
    gradient: "from-emerald-600 to-teal-800",
    emoji: "🛋️",
    categorySlug: "furniture",
    stock: 8,
    images: [
      { id: 1, emoji: "🛋️", gradient: "from-emerald-600 to-teal-800", alt: "Modern 3-Seater Sofa Set" },
      { id: 2, emoji: "🏡", gradient: "from-teal-700 to-emerald-800", alt: "Living room setup" },
    ],
    features: ["L-Shaped Design", "High-Density Foam", "Stain Resistant", "Solid Wood Frame"],
  },
  {
    id: 12,
    slug: "non-stick-cookware-set",
    name: "12-Piece Non-Stick Cookware Set",
    description:
      "Complete cookware set with pots, pans, and lids featuring triple-layer non-stick coating. Compatible with gas, electric, and induction cooktops. Dishwasher safe.",
    price: 145000,
    oldPrice: 220000,
    rating: 4.8,
    reviews: 923,
    badge: "Kitchen Essential",
    gradient: "from-orange-500 to-red-600",
    emoji: "🍳",
    categorySlug: "home-kitchen",
    stock: 45,
    images: [
      { id: 1, emoji: "🍳", gradient: "from-orange-500 to-red-600", alt: "Non-Stick Cookware Set" },
      { id: 2, emoji: "🥘", gradient: "from-red-500 to-orange-600", alt: "Full set layout" },
    ],
    features: ["12-Piece Set", "Triple-Layer Non-Stick", "Induction Compatible", "Dishwasher Safe"],
  },
  {
    id: 13,
    slug: "educational-toy-set",
    name: "STEM Educational Toy Set",
    description:
      "Interactive building blocks and puzzle set designed for ages 3–8. Develops motor skills, creativity, and problem-solving. Non-toxic ABS plastic with smooth edges.",
    price: 35000,
    oldPrice: 55000,
    rating: 4.9,
    reviews: 1245,
    badge: "Parent Pick",
    gradient: "from-sky-400 to-blue-600",
    emoji: "🧩",
    categorySlug: "kids-baby",
    stock: 72,
    images: [
      { id: 1, emoji: "🧩", gradient: "from-sky-400 to-blue-600", alt: "STEM Educational Toy Set" },
      { id: 2, emoji: "🎨", gradient: "from-blue-500 to-indigo-600", alt: "Creative play" },
    ],
    features: ["Ages 3–8", "Non-Toxic ABS", "Motor Skills Development", "120+ Pieces"],
  },
  {
    id: 14,
    slug: "matte-lipstick-set",
    name: "Matte Lipstick Set (6 Colors)",
    description:
      "Long-lasting matte lipstick collection with 6 trending shades. Enriched with vitamin E and jojoba oil for comfortable wear. Cruelty-free and paraben-free formula.",
    price: 28000,
    oldPrice: 48000,
    rating: 4.6,
    reviews: 1567,
    badge: "Beauty Deal",
    gradient: "from-rose-400 to-red-500",
    emoji: "💋",
    categorySlug: "beauty",
    stock: 58,
    images: [
      { id: 1, emoji: "💋", gradient: "from-rose-400 to-red-500", alt: "Matte Lipstick Set" },
      { id: 2, emoji: "💄", gradient: "from-red-400 to-rose-500", alt: "Color swatches" },
    ],
    features: ["6 Trending Shades", "Long-Lasting Matte", "Vitamin E Enriched", "Cruelty-Free"],
  },
  {
    id: 15,
    slug: "office-desk-chair",
    name: "Ergonomic Office Desk Chair",
    description:
      "Adjustable ergonomic office chair with lumbar support, breathable mesh back, and 360° swivel. Height-adjustable armrests and smooth-rolling casters for home or office.",
    price: 210000,
    oldPrice: 320000,
    rating: 4.7,
    reviews: 389,
    badge: "Office Pick",
    gradient: "from-zinc-600 to-zinc-800",
    emoji: "🪑",
    categorySlug: "furniture",
    stock: 19,
    images: [
      { id: 1, emoji: "🪑", gradient: "from-zinc-600 to-zinc-800", alt: "Ergonomic Office Desk Chair" },
      { id: 2, emoji: "💼", gradient: "from-zinc-700 to-slate-800", alt: "Office setup" },
    ],
    features: ["Lumbar Support", "Breathable Mesh", "360° Swivel", "Height Adjustable"],
  },
  {
    id: 16,
    slug: "stainless-steel-sink",
    name: "Stainless Steel Kitchen Sink",
    description:
      "Double-bowl stainless steel kitchen sink with drainboard. 304-grade steel, anti-scratch finish, and sound-deadening pads. Includes strainer and mounting clips.",
    price: 165000,
    oldPrice: 240000,
    rating: 4.5,
    reviews: 267,
    badge: "Contractor Choice",
    gradient: "from-stone-500 to-zinc-700",
    emoji: "🚿",
    categorySlug: "building-materials",
    stock: 34,
    images: [
      { id: 1, emoji: "🚿", gradient: "from-stone-500 to-zinc-700", alt: "Stainless Steel Kitchen Sink" },
      { id: 2, emoji: "🔧", gradient: "from-zinc-600 to-stone-600", alt: "Installation kit" },
    ],
    features: ["304-Grade Steel", "Double Bowl", "Anti-Scratch Finish", "Sound Deadening"],
  },
  {
    id: 17,
    slug: "cotton-bedsheet-set",
    name: "Premium Cotton Bedsheet Set",
    description:
      "100% Egyptian cotton bedsheet set including fitted sheet, flat sheet, and 2 pillowcases. 400 thread count, breathable and soft. Available in queen and king sizes.",
    price: 72000,
    oldPrice: 115000,
    rating: 4.8,
    reviews: 876,
    badge: "Comfort Pick",
    gradient: "from-indigo-300 to-purple-500",
    emoji: "🛏️",
    categorySlug: "home-kitchen",
    stock: 63,
    images: [
      { id: 1, emoji: "🛏️", gradient: "from-indigo-300 to-purple-500", alt: "Premium Cotton Bedsheet Set" },
      { id: 2, emoji: "🌙", gradient: "from-purple-400 to-indigo-400", alt: "Bedroom styled" },
    ],
    features: ["100% Egyptian Cotton", "400 Thread Count", "Queen & King Sizes", "Machine Washable"],
  },
  {
    id: 18,
    slug: "baby-stroller-combo",
    name: "3-in-1 Baby Stroller Combo",
    description:
      "Versatile 3-in-1 stroller system with car seat adapter, reversible seat, and large storage basket. One-hand fold mechanism and all-terrain wheels for smooth rides.",
    price: 320000,
    oldPrice: 450000,
    rating: 4.7,
    reviews: 445,
    badge: "Baby Essential",
    gradient: "from-pink-300 to-rose-500",
    emoji: "👶",
    categorySlug: "kids-baby",
    stock: 14,
    images: [
      { id: 1, emoji: "👶", gradient: "from-pink-300 to-rose-500", alt: "3-in-1 Baby Stroller Combo" },
      { id: 2, emoji: "🍼", gradient: "from-rose-400 to-pink-400", alt: "With accessories" },
    ],
    features: ["3-in-1 System", "One-Hand Fold", "All-Terrain Wheels", "Car Seat Adapter"],
  },
  {
    id: 19,
    slug: "silk-scarf-collection",
    name: "Silk Scarf Collection (3pc)",
    description:
      "Luxurious silk-blend scarf set with three unique prints. Lightweight and versatile — wear as neck scarf, head wrap, or bag accessory. Hand-rolled edges.",
    price: 32000,
    oldPrice: 58000,
    rating: 4.5,
    reviews: 612,
    badge: "Accessory",
    gradient: "from-fuchsia-400 to-purple-600",
    emoji: "🧣",
    categorySlug: "womens-fashion",
    stock: 44,
    images: [
      { id: 1, emoji: "🧣", gradient: "from-fuchsia-400 to-purple-600", alt: "Silk Scarf Collection" },
    ],
    features: ["Silk Blend", "3 Unique Prints", "Hand-Rolled Edges", "Versatile Styling"],
  },
  {
    id: 20,
    slug: "bluetooth-speaker",
    name: "Portable Bluetooth Speaker",
    description:
      "360° surround sound portable speaker with 24-hour playtime. IP67 waterproof and dustproof. Party mode to connect multiple speakers. Built-in microphone for calls.",
    price: 62000,
    oldPrice: 98000,
    rating: 4.7,
    reviews: 1890,
    badge: "Audio Deal",
    gradient: "from-indigo-500 to-violet-700",
    emoji: "🔊",
    categorySlug: "electronics",
    stock: 56,
    images: [
      { id: 1, emoji: "🔊", gradient: "from-indigo-500 to-violet-700", alt: "Portable Bluetooth Speaker" },
      { id: 2, emoji: "🎵", gradient: "from-violet-600 to-purple-700", alt: "Party mode" },
    ],
    features: ["360° Surround Sound", "24hr Playtime", "IP67 Waterproof", "Party Mode"],
  },
  {
    id: 21,
    slug: "formal-dress-shirt",
    name: "Formal Dress Shirt",
    description:
      "Classic fit formal dress shirt in wrinkle-resistant cotton blend. Spread collar, barrel cuffs, and mother-of-pearl buttons. Perfect for office and special occasions.",
    price: 45000,
    oldPrice: 75000,
    rating: 4.4,
    reviews: 723,
    badge: "Office Wear",
    gradient: "from-blue-600 to-indigo-800",
    emoji: "👔",
    categorySlug: "mens-fashion",
    stock: 67,
    images: [
      { id: 1, emoji: "👔", gradient: "from-blue-600 to-indigo-800", alt: "Formal Dress Shirt" },
    ],
    features: ["Wrinkle Resistant", "Spread Collar", "Cotton Blend", "Classic Fit"],
  },
  {
    id: 22,
    slug: "facial-cleansing-brush",
    name: "Sonic Facial Cleansing Brush",
    description:
      "Waterproof sonic cleansing brush with 3 speed settings and 2 brush heads. Deep pore cleansing for smoother, brighter skin. USB rechargeable with 30-day battery.",
    price: 48000,
    oldPrice: 82000,
    rating: 4.6,
    reviews: 934,
    badge: "Skincare Tool",
    gradient: "from-teal-400 to-cyan-600",
    emoji: "🪥",
    categorySlug: "beauty",
    stock: 39,
    images: [
      { id: 1, emoji: "🪥", gradient: "from-teal-400 to-cyan-600", alt: "Sonic Facial Cleansing Brush" },
    ],
    features: ["3 Speed Settings", "Waterproof", "2 Brush Heads", "USB Rechargeable"],
  },
  {
    id: 23,
    slug: "dining-table-set",
    name: "6-Seater Dining Table Set",
    description:
      "Solid wood dining table with 6 upholstered chairs. Scratch-resistant tabletop with natural wood grain finish. Compact design ideal for modern dining rooms.",
    price: 750000,
    oldPrice: 980000,
    rating: 4.5,
    reviews: 98,
    badge: "Home Set",
    gradient: "from-amber-700 to-yellow-900",
    emoji: "🪵",
    categorySlug: "furniture",
    stock: 6,
    images: [
      { id: 1, emoji: "🪵", gradient: "from-amber-700 to-yellow-900", alt: "6-Seater Dining Table Set" },
    ],
    features: ["Solid Wood", "6 Upholstered Chairs", "Scratch Resistant", "Natural Grain Finish"],
  },
  {
    id: 24,
    slug: "ceiling-fan-led",
    name: "52\" Ceiling Fan with LED Light",
    description:
      "Energy-efficient 52-inch ceiling fan with integrated dimmable LED light and remote control. 3-speed settings with reversible motor for year-round use.",
    price: 98000,
    oldPrice: 145000,
    rating: 4.4,
    reviews: 312,
    badge: "Home Upgrade",
    gradient: "from-zinc-500 to-slate-700",
    emoji: "🌀",
    categorySlug: "home-kitchen",
    stock: 25,
    images: [
      { id: 1, emoji: "🌀", gradient: "from-zinc-500 to-slate-700", alt: "Ceiling Fan with LED Light" },
    ],
    features: ["52-inch Blades", "Dimmable LED Light", "Remote Control", "Reversible Motor"],
  },
];

export const products: Product[] = productSeeds.map((p, i) => ({
  ...p,
  featured: i < 4,
  status: "active" as ProductStatus,
}));

export function getProductBySlug(slug: string): Product | undefined {
  return products.find((p) => p.slug === slug);
}

export function getProductById(id: number): Product | undefined {
  return products.find((p) => p.id === id);
}

export function getProductsByCategory(categorySlug: string): Product[] {
  return products.filter((p) => p.categorySlug === categorySlug);
}

export function getRelatedProducts(product: Product, limit = 4): Product[] {
  return products
    .filter((p) => p.categorySlug === product.categorySlug && p.id !== product.id)
    .slice(0, limit);
}

export function getFeaturedProducts(limit = 8): Product[] {
  return products.filter((p) => p.featured && p.status === "active").slice(0, limit);
}

export function searchProducts(query: string): Product[] {
  const q = query.toLowerCase().trim();
  if (!q) return products;
  return products.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      p.description.toLowerCase().includes(q) ||
      p.categorySlug.includes(q),
  );
}

export function sortProducts(items: Product[], sort: SortOption): Product[] {
  const sorted = [...items];
  switch (sort) {
    case "price-asc":
      return sorted.sort((a, b) => a.price - b.price);
    case "price-desc":
      return sorted.sort((a, b) => b.price - a.price);
    case "rating":
      return sorted.sort((a, b) => b.rating - a.rating);
    case "newest":
      return sorted.sort((a, b) => b.id - a.id);
    default:
      return sorted;
  }
}

export function filterProducts(
  items: Product[],
  options: { category?: string; minPrice?: number; maxPrice?: number; inStock?: boolean },
): Product[] {
  return items.filter((p) => {
    if (options.category && p.categorySlug !== options.category) return false;
    if (options.minPrice !== undefined && p.price < options.minPrice) return false;
    if (options.maxPrice !== undefined && p.price > options.maxPrice) return false;
    if (options.inStock && p.stock <= 0) return false;
    return true;
  });
}
