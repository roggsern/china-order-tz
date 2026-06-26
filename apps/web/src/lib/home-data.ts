export { categories, megaMenuCategories, getSubcategories, getFeaturedForCategory } from "@/lib/catalog/categories";
export { buyFromTzBrands } from "@/lib/catalog/brands";
export { formatPrice } from "@/lib/catalog/utils";

import { buyFromTzBrands } from "@/lib/catalog/brands";

export const navLinks = [
  { label: "How It Works", href: "/#how-it-works" },
  { label: "About", href: "/#about" },
  { label: "Contact", href: "/#contact" },
] as const;

export const headerSecondaryNav = [
  { label: "About Us", href: "/#about" },
  { label: "Contact Us", href: "/#contact" },
] as const;

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
    title: "Verified Suppliers",
    description: "Every partner is vetted for quality, reliability, and export compliance.",
    icon: "shield",
  },
  {
    title: "Affordable Prices",
    description: "Factory-direct pricing with transparent TZS quotes — no hidden markups.",
    icon: "tag",
  },
  {
    title: "Fast Shipping",
    description: "Air and sea freight from China to Tanzania with real-time tracking.",
    icon: "shipping",
  },
  {
    title: "Customer Support",
    description: "Dedicated support team available via phone, email, and WhatsApp.",
    icon: "support",
  },
] as const;

export const footerLinks = {
  about: [
    { label: "Our Story", href: "/#about" },
    { label: "Why Choose Us", href: "/#about" },
    { label: "Order From China", href: "/#order-from-china" },
  ],
  contact: [
    { label: "hello@chinaordertz.com", href: "mailto:hello@chinaordertz.com" },
    { label: "+255 123 456 789", href: "tel:+255123456789" },
    { label: "Dar es Salaam, Tanzania", href: "/#contact" },
  ],
  quickLinks: [
    { label: "All Categories", href: "/categories" },
    { label: "Featured Products", href: "/#products" },
    { label: "Shop All", href: "/products" },
    { label: "Login", href: "/login" },
  ],
  buyFromTz: buyFromTzBrands.map((brand) => ({
    label: brand.label,
    href: `/products?brand=${brand.slug}`,
  })),
} as const;
