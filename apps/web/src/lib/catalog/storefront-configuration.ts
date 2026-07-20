import type { ProductFormSchemaAttribute } from "@/lib/types/catalog";

export type StorefrontConfigurationValue = {
  id: string;
  product_attribute_id: string;
  value: string;
  slug: string;
  color_code?: string | null;
  attribute_slug?: string | null;
  attribute_name?: string | null;
};

export type StorefrontConfiguration = {
  id: string;
  sku: string;
  name: string;
  price?: string | number | null;
  attribute_value_ids: string[];
  attribute_values: StorefrontConfigurationValue[];
  stock: number;
  in_stock: boolean;
};

export type StorefrontConfigurationExperience = {
  product_id: string;
  product_type: {
    id: string;
    name: string;
    slug: string;
    sku_pattern?: string | null;
  } | null;
  capabilities: {
    has_configurations: boolean;
    allows_price_override: boolean;
    allows_moq_pricing: boolean;
  };
  attributes: ProductFormSchemaAttribute[];
  dependencies: Array<{
    id: string;
    source_attribute_id: string;
    target_attribute_id: string;
    rules: Array<{
      id: string;
      source_attribute_value_id: string;
      target_attribute_value_id: string;
    }>;
  }>;
  configurations: StorefrontConfiguration[];
  allowed_value_ids: Record<string, string[]>;
  matched_configuration_id: string | null;
  is_complete: boolean;
  is_in_stock: boolean;
  has_configurations: boolean;
};

export type StorefrontPriceQuote = {
  product_id: string;
  configuration_id: string | null;
  quantity: number;
  currency: string;
  unit_price: string;
  line_total: string;
  breakdown: Array<{
    stage: string;
    label: string;
    unit_price: string;
    applied: boolean;
    note?: string | null;
    meta?: Record<string, unknown>;
  }>;
};

export class StorefrontConfigurationApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "StorefrontConfigurationApiError";
  }
}

async function parseJson<T>(response: Response): Promise<T> {
  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: T;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    throw new StorefrontConfigurationApiError(
      payload.message?.trim() || "Unable to load product configuration.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchStorefrontConfiguration(
  slug: string,
  selections: Record<string, string> = {},
): Promise<StorefrontConfigurationExperience> {
  const params = new URLSearchParams();
  Object.entries(selections).forEach(([attributeId, valueId]) => {
    params.append(`selections[${attributeId}]`, valueId);
  });

  const query = params.toString();
  const response = await fetch(
    `/api/catalog/products/${encodeURIComponent(slug)}/configuration${query ? `?${query}` : ""}`,
    {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  return parseJson<StorefrontConfigurationExperience>(response);
}

export async function fetchStorefrontQuote(input: {
  slug: string;
  configurationId?: string | null;
  quantity: number;
}): Promise<StorefrontPriceQuote> {
  const response = await fetch(
    `/api/catalog/products/${encodeURIComponent(input.slug)}/quote`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        configuration_id: input.configurationId ?? null,
        quantity: input.quantity,
      }),
      cache: "no-store",
    },
  );

  return parseJson<StorefrontPriceQuote>(response);
}
