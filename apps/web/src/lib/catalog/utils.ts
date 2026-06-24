export function formatPrice(amount: number): string {
  return new Intl.NumberFormat("en-TZ", {
    style: "currency",
    currency: "TZS",
    maximumFractionDigits: 0,
  }).format(amount);
}

export function calculateDiscount(price: number, oldPrice: number): number {
  if (oldPrice <= 0 || price >= oldPrice) return 0;
  return Math.round(((oldPrice - price) / oldPrice) * 100);
}

export function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");
}

export function getStockStatus(stock: number): {
  label: string;
  variant: "in-stock" | "low-stock" | "out-of-stock";
} {
  if (stock <= 0) return { label: "Out of Stock", variant: "out-of-stock" };
  if (stock <= 10) return { label: `Only ${stock} left`, variant: "low-stock" };
  return { label: "In Stock", variant: "in-stock" };
}
