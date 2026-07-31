import { getCustomerApiToken } from "@/lib/api/customer-auth";
import type { ShippingAddress } from "@/lib/types/checkout";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  meta?: {
    default_id?: string | null;
  };
  errors?: Record<string, string[]>;
};

export type CustomerAddress = {
  id: string;
  label?: string | null;
  recipient_name: string;
  phone: string;
  street: string;
  district?: string | null;
  address_line_1?: string;
  address_line_2?: string | null;
  city: string;
  region: string;
  postal_code?: string | null;
  country: string;
  is_shipping?: boolean;
  is_billing?: boolean;
  is_default: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export type CustomerAddressInput = {
  label?: string | null;
  recipient_name: string;
  phone: string;
  street: string;
  district: string;
  city: string;
  region: string;
  country?: string;
  postal_code?: string | null;
  is_default?: boolean;
};

export class CustomerAddressesApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly fieldErrors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerAddressesApiError";
  }
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

async function customerFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<{ data: T; meta?: ApiSuccessResponse<T>["meta"]; message?: string }> {
  const authToken = getCustomerApiToken();
  if (!authToken) {
    throw new CustomerAddressesApiError("Sign in to manage saved addresses.", 401);
  }

  const response = await fetch(path, {
    ...init,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...(init.headers ?? {}),
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new CustomerAddressesApiError(
      formatError(payload, fallback),
      response.status,
      payload.errors,
    );
  }

  return { data: payload.data as T, meta: payload.meta, message: payload.message };
}

export async function fetchCustomerAddresses(): Promise<{
  addresses: CustomerAddress[];
  defaultId: string | null;
}> {
  const { data, meta } = await customerFetch<CustomerAddress[]>(
    "/api/account/addresses",
    { method: "GET" },
    "Unable to load saved addresses.",
  );

  return {
    addresses: Array.isArray(data) ? data : [],
    defaultId: meta?.default_id ?? null,
  };
}

export async function createCustomerAddress(
  input: CustomerAddressInput,
): Promise<CustomerAddress> {
  const { data } = await customerFetch<CustomerAddress>(
    "/api/account/addresses",
    { method: "POST", body: JSON.stringify(input) },
    "Unable to save address.",
  );
  return data;
}

export async function updateCustomerAddress(
  id: string,
  input: Partial<CustomerAddressInput>,
): Promise<CustomerAddress> {
  const { data } = await customerFetch<CustomerAddress>(
    `/api/account/addresses/${encodeURIComponent(id)}`,
    { method: "PUT", body: JSON.stringify(input) },
    "Unable to update address.",
  );
  return data;
}

export async function deleteCustomerAddress(id: string): Promise<void> {
  await customerFetch<unknown>(
    `/api/account/addresses/${encodeURIComponent(id)}`,
    { method: "DELETE" },
    "Unable to delete address.",
  );
}

export async function setDefaultCustomerAddress(id: string): Promise<CustomerAddress> {
  const { data } = await customerFetch<CustomerAddress>(
    `/api/account/addresses/${encodeURIComponent(id)}/default`,
    { method: "PATCH" },
    "Unable to set default address.",
  );
  return data;
}

export function mapCustomerAddressToShipping(address: CustomerAddress): ShippingAddress {
  return {
    addressLine1: address.street?.trim() || address.address_line_1?.trim() || "",
    addressLine2: address.district?.trim() || address.address_line_2?.trim() || "",
    city: address.city?.trim() || "",
    region: address.region?.trim() || "",
    postalCode: address.postal_code?.trim() || "",
    country: address.country?.trim() || "Tanzania",
  };
}

export function pickDefaultCustomerAddress(
  addresses: CustomerAddress[],
  defaultId?: string | null,
): CustomerAddress | null {
  if (!addresses.length) return null;
  if (defaultId) {
    const match = addresses.find((row) => row.id === defaultId);
    if (match) return match;
  }
  return addresses.find((row) => row.is_default) ?? addresses[0] ?? null;
}
