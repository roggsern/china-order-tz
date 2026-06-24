export { categories, megaMenuCategories, getSubcategories, getFeaturedForCategory } from "@/lib/catalog/categories";
export { formatPrice } from "@/lib/catalog/utils";

import { getFeaturedProducts } from "@/lib/catalog/products";

export const navLinks = [
  { label: "Home", href: "/#home" },
  { label: "How It Works", href: "/#how-it-works" },
  { label: "Order From China", href: "/#order-from-china" },
  { label: "About", href: "/#about" },
  { label: "Contact", href: "/#contact" },
] as const;

export const featuredProducts = getFeaturedProducts(8).map((p) => ({
  id: p.id,
  name: p.name,
  price: p.price,
  oldPrice: p.oldPrice,
  rating: p.rating,
  reviews: p.reviews,
  badge: p.badge,
  gradient: p.gradient,
  emoji: p.emoji,
  slug: p.slug,
}));

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
    { label: "All Categories", href: "/categories" },
    { label: "Featured Deals", href: "/products" },
    { label: "New Arrivals", href: "/products?sort=newest" },
    { label: "Bulk Orders", href: "/#contact" },
  ],
  company: [
    { label: "About Us", href: "/#about" },
    { label: "How It Works", href: "/#how-it-works" },
    { label: "Order From China", href: "/#order-from-china" },
    { label: "Contact", href: "/#contact" },
  ],
  support: [
    { label: "Help Center", href: "/#contact" },
    { label: "Track Order", href: "/#contact" },
    { label: "Returns", href: "/#contact" },
    { label: "Privacy Policy", href: "/#contact" },
  ],
} as const;
