/** Display-only: comma-separated integer, never modifies the stored value. */
export function formatPriceOnly(value: number): string {
  if (!Number.isFinite(value)) return "0";
  return Math.round(value).toLocaleString("en-TZ");
}

export function formatPrice(amount: number): string {
  return `TZS ${formatPriceOnly(amount)}`;
}

/** Display-only: append "Days" suffix without modifying stored values. */
export function formatDays(value: string | number | null | undefined): string {
  if (value == null) return "";

  const text =
    typeof value === "number"
      ? Number.isFinite(value)
        ? String(value)
        : ""
      : value.trim();

  if (!text) return "";
  if (text === "—") return text;

  if (/\bdays\b/i.test(text)) {
    const base = text.replace(/\s*days?\s*$/i, "").trim();
    return base ? `${base} Days` : "";
  }

  return `${text} Days`;
}

/** Human-readable delivery window e.g. "35–45 days" without duplicated suffix. */
export function formatDeliveryEstimate(value: string | number | null | undefined): string {
  if (value == null) return "—";

  const text = (typeof value === "number" ? String(value) : value).trim();
  if (!text || text === "—") return "—";

  const base = text.replace(/\s*days?\s*$/i, "").trim();
  if (!base) return "—";

  const normalized = base.replace(/\s*-\s*/g, "–");
  return `${normalized} days`;
}

export function calculateDiscount(price: number, oldPrice: number): number {
  if (oldPrice <= 0 || price >= oldPrice) return 0;
  return Math.floor(((oldPrice - price) / oldPrice) * 100);
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
