export type CatalogHealthSeverity = "critical" | "warning" | "info";
export type CatalogHealthPriority = "P0" | "P1" | "P2";

export type CatalogHealthIssueBucket = {
  severity: CatalogHealthSeverity;
  priority: CatalogHealthPriority;
  count: number;
  product_ids?: string[];
  variant_ids?: string[];
};

export type CatalogHealthSummary = {
  health_score: number;
  critical_count: number;
  warning_count: number;
};

export type CatalogHealthIssues = {
  commerce_readiness: {
    active_not_purchasable: CatalogHealthIssueBucket;
    missing_valid_price: CatalogHealthIssueBucket;
    variants_missing_valid_price?: CatalogHealthIssueBucket;
  };
  media: {
    active_public_without_images: CatalogHealthIssueBucket;
    variants_without_media: CatalogHealthIssueBucket;
  };
  inventory: {
    active_missing_inventory_policy: CatalogHealthIssueBucket;
    variants_missing_inventory_policy?: CatalogHealthIssueBucket;
  };
  catalog_quality: {
    variants_without_sku: CatalogHealthIssueBucket;
    variants_without_barcode: CatalogHealthIssueBucket;
    missing_descriptions: CatalogHealthIssueBucket;
  };
};

export type CatalogHealthPayload = {
  summary: CatalogHealthSummary;
  issues: CatalogHealthIssues;
};

export class AdminCatalogHealthApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminCatalogHealthApiError";
  }
}

type ApiEnvelope = {
  success?: boolean;
  message?: string;
  data?: CatalogHealthPayload;
};

export async function fetchCatalogHealth(): Promise<CatalogHealthPayload> {
  const response = await fetch("/api/admin/catalog-health", {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json().catch(() => ({}))) as ApiEnvelope;

  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminCatalogHealthApiError(
      payload.message?.trim() || "Unable to load catalog health.",
      response.status,
    );
  }

  return payload.data;
}
