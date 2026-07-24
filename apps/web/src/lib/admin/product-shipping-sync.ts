export type ProductShippingModeDraft = {
  available: boolean;
  price: number;
  notes: string;
};

export type ProductShippingFormState = {
  air: ProductShippingModeDraft;
  sea: ProductShippingModeDraft;
};

export type ProductShippingOptionRow = {
  transport_mode: "air" | "sea";
  price: number;
  currency?: string;
  is_available?: boolean;
  notes?: string | null;
  sort_order?: number;
};

export type AdminProductShippingOptionLike = {
  transportMode: "air" | "sea";
  price: number;
  isAvailable: boolean;
  notes: string;
};

export const emptyProductShippingFormState = (): ProductShippingFormState => ({
  air: { available: true, price: 18000, notes: "" },
  sea: { available: true, price: 9500, notes: "" },
});

export function isChinaImportCommerceChannel(code: string | null | undefined): boolean {
  if (!code) {
    return false;
  }

  return code.trim().toUpperCase().replace(/-/g, "_") === "CHINA_IMPORT";
}

export function isTzLocalCommerceChannel(code: string | null | undefined): boolean {
  if (!code) {
    return false;
  }

  return code.trim().toUpperCase().replace(/-/g, "_") === "TZ_LOCAL";
}

export function hasPublishableShippingOption(
  options: ReadonlyArray<AdminProductShippingOptionLike>,
): boolean {
  return options.some((option) => option.isAvailable && option.price > 0);
}

export function mapShippingOptionsToFormState(
  options: ReadonlyArray<AdminProductShippingOptionLike>,
): ProductShippingFormState {
  const air = options.find((option) => option.transportMode === "air");
  const sea = options.find((option) => option.transportMode === "sea");

  return {
    air: {
      available: air?.isAvailable ?? false,
      price: air?.price ?? 0,
      notes: air?.notes ?? "",
    },
    sea: {
      available: sea?.isAvailable ?? false,
      price: sea?.price ?? 0,
      notes: sea?.notes ?? "",
    },
  };
}

export function buildProductShippingSyncPayload(
  form: ProductShippingFormState,
): { shipping_options: ProductShippingOptionRow[] } {
  const rows: ProductShippingOptionRow[] = [];

  if (form.air.available && form.air.price > 0) {
    rows.push({
      transport_mode: "air",
      price: form.air.price,
      currency: "TZS",
      is_available: true,
      notes: form.air.notes.trim() || null,
      sort_order: 0,
    });
  }

  if (form.sea.available && form.sea.price > 0) {
    rows.push({
      transport_mode: "sea",
      price: form.sea.price,
      currency: "TZS",
      is_available: true,
      notes: form.sea.notes.trim() || null,
      sort_order: 1,
    });
  }

  return { shipping_options: rows };
}

export function validateProductShippingFormState(
  form: ProductShippingFormState,
): Record<string, string> {
  const errors: Record<string, string> = {};

  if (form.air.available && form.air.price <= 0) {
    errors.airPrice = "Air shipping price is required when Air is available.";
  }

  if (form.sea.available && form.sea.price <= 0) {
    errors.seaPrice = "Sea shipping price is required when Sea is available.";
  }

  const airOk = form.air.available && form.air.price > 0;
  const seaOk = form.sea.available && form.sea.price > 0;

  if (!airOk && !seaOk) {
    errors.shipping =
      "Enable at least one shipping mode with a price greater than zero.";
  }

  return errors;
}
