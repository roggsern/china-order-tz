import { hasAdminPermission } from "@/lib/api/admin-me";
import type {
  CatalogHealthIssueBucket,
  CatalogHealthIssues,
  CatalogHealthPayload,
  CatalogHealthPriority,
  CatalogHealthSeverity,
  CatalogHealthSummary,
} from "@/lib/api/admin-catalog-health";
import type { CatalogProductEditTab } from "@/lib/admin/product-id-map";
import { buildCatalogProductEditUrl } from "@/lib/admin/product-id-map";

export type CatalogHealthMetricKey =
  | "active_not_purchasable"
  | "missing_valid_price"
  | "variants_missing_valid_price"
  | "active_public_without_images"
  | "variants_without_media"
  | "active_missing_inventory_policy"
  | "variants_missing_inventory_policy"
  | "variants_without_sku"
  | "variants_without_barcode"
  | "missing_descriptions";

export type CatalogHealthGroupId =
  | "commerce_readiness"
  | "media"
  | "inventory"
  | "catalog_quality";

export type CatalogHealthMetricView = {
  key: CatalogHealthMetricKey;
  label: string;
  severity: CatalogHealthSeverity;
  priority: CatalogHealthPriority;
  count: number;
  productIds: string[];
  variantIds: string[];
  editTab?: CatalogProductEditTab;
};

export type CatalogHealthGroupView = {
  id: CatalogHealthGroupId;
  title: string;
  description: string;
  metrics: CatalogHealthMetricView[];
};

export type CatalogHealthReportView = {
  summary: CatalogHealthSummary;
  groups: CatalogHealthGroupView[];
  isEmpty: boolean;
};

const EMPTY_BUCKET: CatalogHealthIssueBucket = {
  severity: "info",
  priority: "P2",
  count: 0,
  product_ids: [],
  variant_ids: [],
};

function asBucket(value: unknown): CatalogHealthIssueBucket {
  if (!value || typeof value !== "object") {
    return { ...EMPTY_BUCKET };
  }

  const row = value as Partial<CatalogHealthIssueBucket>;
  const severity =
    row.severity === "critical" || row.severity === "warning" || row.severity === "info"
      ? row.severity
      : "info";
  const priority =
    row.priority === "P0" || row.priority === "P1" || row.priority === "P2" ? row.priority : "P2";

  return {
    severity,
    priority,
    count: Math.max(0, Number(row.count ?? 0)),
    product_ids: Array.isArray(row.product_ids)
      ? row.product_ids.filter((id): id is string => typeof id === "string" && id.trim() !== "")
      : [],
    variant_ids: Array.isArray(row.variant_ids)
      ? row.variant_ids.filter((id): id is string => typeof id === "string" && id.trim() !== "")
      : [],
  };
}

function metric(
  key: CatalogHealthMetricKey,
  label: string,
  bucket: CatalogHealthIssueBucket,
  editTab?: CatalogProductEditTab,
): CatalogHealthMetricView {
  return {
    key,
    label,
    severity: bucket.severity,
    priority: bucket.priority,
    count: bucket.count,
    productIds: bucket.product_ids ?? [],
    variantIds: bucket.variant_ids ?? [],
    editTab,
  };
}

export function mapCatalogHealthPayload(payload: CatalogHealthPayload): CatalogHealthReportView {
  const issues = payload.issues ?? ({} as CatalogHealthIssues);
  const commerce = issues.commerce_readiness ?? {};
  const media = issues.media ?? {};
  const inventory = issues.inventory ?? {};
  const quality = issues.catalog_quality ?? {};

  const groups: CatalogHealthGroupView[] = [
    {
      id: "commerce_readiness",
      title: "Commerce readiness",
      description: "Pricing and purchasability gaps that block selling.",
      metrics: [
        metric(
          "missing_valid_price",
          "Missing prices",
          asBucket(commerce.missing_valid_price),
          "details",
        ),
        metric(
          "variants_missing_valid_price",
          "Variants missing prices",
          asBucket(commerce.variants_missing_valid_price),
          "variants",
        ),
        metric(
          "active_not_purchasable",
          "Not purchasable",
          asBucket(commerce.active_not_purchasable),
          "details",
        ),
      ],
    },
    {
      id: "media",
      title: "Media",
      description: "Product and variant image coverage.",
      metrics: [
        metric(
          "active_public_without_images",
          "Missing images",
          asBucket(media.active_public_without_images),
          "media",
        ),
        metric(
          "variants_without_media",
          "Variant media gaps",
          asBucket(media.variants_without_media),
          "variants",
        ),
      ],
    },
    {
      id: "inventory",
      title: "Inventory",
      description: "Missing inventory policy on active products and variants.",
      metrics: [
        metric(
          "active_missing_inventory_policy",
          "Inventory issues",
          asBucket(inventory.active_missing_inventory_policy),
          "stock",
        ),
        metric(
          "variants_missing_inventory_policy",
          "Variants missing inventory",
          asBucket(inventory.variants_missing_inventory_policy),
          "stock",
        ),
      ],
    },
    {
      id: "catalog_quality",
      title: "Catalog quality",
      description: "SKU, barcode, and description completeness.",
      metrics: [
        metric(
          "variants_without_sku",
          "Missing SKU",
          asBucket(quality.variants_without_sku),
          "variants",
        ),
        metric(
          "variants_without_barcode",
          "Missing barcode",
          asBucket(quality.variants_without_barcode),
          "variants",
        ),
        metric(
          "missing_descriptions",
          "Missing descriptions",
          asBucket(quality.missing_descriptions),
          "details",
        ),
      ],
    },
  ];

  const summary: CatalogHealthSummary = {
    health_score: Math.max(0, Math.min(100, Number(payload.summary?.health_score ?? 0))),
    critical_count: Math.max(0, Number(payload.summary?.critical_count ?? 0)),
    warning_count: Math.max(0, Number(payload.summary?.warning_count ?? 0)),
  };

  return {
    summary,
    groups,
    isEmpty: isCatalogHealthEmpty(groups),
  };
}

export function groupCatalogHealthIssues(groups: CatalogHealthGroupView[]): CatalogHealthGroupView[] {
  return groups.map((group) => ({
    ...group,
    metrics: group.metrics.filter((row) => row.count > 0),
  }));
}

export function isCatalogHealthEmpty(groups: CatalogHealthGroupView[]): boolean {
  return groups.every((group) => group.metrics.every((metricRow) => metricRow.count === 0));
}

export function catalogHealthEmptyMessage(): string {
  return "Catalog looks healthy — no critical or warning issues detected.";
}

export function canViewCatalogHealth(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "catalog.view");
}

export function catalogHealthSeverityBadgeClass(severity: CatalogHealthSeverity): string {
  switch (severity) {
    case "critical":
      return "border-red-200 bg-red-50 text-red-700";
    case "warning":
      return "border-amber-200 bg-amber-50 text-amber-800";
    case "info":
      return "border-sky-200 bg-sky-50 text-sky-800";
    default:
      return "border-zinc-200 bg-zinc-50 text-zinc-700";
  }
}

export function catalogHealthScoreTone(score: number): string {
  if (score >= 85) return "text-emerald-700";
  if (score >= 60) return "text-amber-700";
  return "text-red-700";
}

export function catalogHealthProductHref(
  productId: string,
  tab?: CatalogProductEditTab,
): string {
  return buildCatalogProductEditUrl(productId, tab);
}
