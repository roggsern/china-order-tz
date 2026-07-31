import { formatPrice } from "@/lib/catalog/utils";

export type AdminProductListImage = {
  id: string;
  path: string | null;
  url: string | null;
  altText: string | null;
};

export type AdminProductPriceRange = {
  min: number | null;
  max: number | null;
  currency: string;
};

export type AdminProductStockSummary = {
  path: "simple" | "variant" | "none";
  totalAvailable: number;
  variantsInStock: number;
  variantsOutOfStock: number;
};

export function formatAdminChannelBadge(
  code: string | null | undefined,
  adminLabel?: string | null,
): { label: string; className: string } | null {
  if (!code) {
    return null;
  }

  const normalized = code.toUpperCase();
  if (normalized === "CHINA_IMPORT") {
    return {
      label: adminLabel?.trim() || "China Import",
      className: "bg-red-50 text-red-700",
    };
  }

  if (normalized === "TZ_LOCAL") {
    return {
      label: adminLabel?.trim() || "Buy From TZ",
      className: "bg-emerald-50 text-emerald-700",
    };
  }

  return {
    label: adminLabel?.trim() || normalized,
    className: "bg-zinc-100 text-zinc-600",
  };
}

export function formatAdminPriceRange(range: AdminProductPriceRange | null | undefined): string {
  if (!range || range.min == null) {
    return "—";
  }

  if (range.max == null || range.min === range.max) {
    return formatPrice(range.min);
  }

  return `${formatPrice(range.min)} – ${formatPrice(range.max)}`;
}

export function formatAdminStockSummary(
  summary: AdminProductStockSummary | null | undefined,
  variantsCount = 0,
): string {
  if (!summary) {
    return "—";
  }

  if (summary.path === "variant") {
    const inStock = summary.variantsInStock;
    const total = Math.max(variantsCount, inStock + summary.variantsOutOfStock);
    return `${summary.totalAvailable} avail · ${inStock}/${total || variantsCount} in stock`;
  }

  return `${summary.totalAvailable} available`;
}

export function adminProductThumbnailUrl(
  image: AdminProductListImage | null | undefined,
): string | null {
  if (!image) {
    return null;
  }

  if (image.url?.trim()) {
    return image.url.trim();
  }

  if (image.path?.trim()) {
    const path = image.path.trim().replace(/^\/+/, "");
    return path.startsWith("storage/") ? `/${path}` : `/storage/${path}`;
  }

  return null;
}

export function parseAdminPriceRange(raw: unknown): AdminProductPriceRange | null {
  if (!raw || typeof raw !== "object") {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const min = row.min == null || row.min === "" ? null : Number(row.min);
  const max = row.max == null || row.max === "" ? null : Number(row.max);

  return {
    min: min != null && Number.isFinite(min) ? min : null,
    max: max != null && Number.isFinite(max) ? max : null,
    currency: typeof row.currency === "string" ? row.currency : "TZS",
  };
}

export function parseAdminStockSummary(raw: unknown): AdminProductStockSummary | null {
  if (!raw || typeof raw !== "object") {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const pathRaw = typeof row.path === "string" ? row.path : "simple";
  const path =
    pathRaw === "variant" || pathRaw === "none" || pathRaw === "simple" ? pathRaw : "simple";

  return {
    path,
    totalAvailable: Number(row.total_available ?? 0) || 0,
    variantsInStock: Number(row.variants_in_stock ?? 0) || 0,
    variantsOutOfStock: Number(row.variants_out_of_stock ?? 0) || 0,
  };
}
