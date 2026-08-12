import { apiClient } from '@/src/core/api';

export type CustomerAddress = {
  id: string;
  label: string | null;
  recipientName: string;
  phone: string;
  street: string;
  district: string | null;
  city: string;
  region: string;
  postalCode: string | null;
  country: string | null;
  isDefault: boolean;
};

export type AddressInput = {
  label?: string | null;
  recipient_name: string;
  phone: string;
  street: string;
  district: string;
  city: string;
  region: string;
  country?: string | null;
  postal_code?: string | null;
  is_default?: boolean;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapCustomerAddress(raw: unknown): CustomerAddress | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  const recipientName =
    stringField(data, 'recipient_name') ?? stringField(data, 'name');
  const phone = stringField(data, 'phone');
  const street =
    stringField(data, 'street') ?? stringField(data, 'address_line_1');
  const city = stringField(data, 'city');
  const region = stringField(data, 'region');
  if (!id || !recipientName || !phone || !street || !city || !region) {
    return null;
  }
  return {
    id,
    label: stringField(data, 'label'),
    recipientName,
    phone,
    street,
    district:
      stringField(data, 'district') ?? stringField(data, 'address_line_2'),
    city,
    region,
    postalCode: stringField(data, 'postal_code'),
    country: stringField(data, 'country'),
    isDefault: data.is_default === true,
  };
}

export async function fetchCustomerAddresses(): Promise<{
  addresses: CustomerAddress[];
  defaultId: string | null;
}> {
  const response = await apiClient.get<unknown>('/account/addresses');
  const rows = Array.isArray(response.data) ? response.data : [];
  const meta = asRecord(response.meta);
  const defaultId =
    typeof meta.default_id === 'string' || typeof meta.default_id === 'number'
      ? String(meta.default_id)
      : null;
  return {
    addresses: rows
      .map(mapCustomerAddress)
      .filter((row): row is CustomerAddress => row !== null),
    defaultId,
  };
}

export async function createCustomerAddress(
  input: AddressInput,
): Promise<CustomerAddress | null> {
  const response = await apiClient.post<unknown>('/account/addresses', input);
  return mapCustomerAddress(response.data);
}

export async function updateCustomerAddress(
  addressId: string,
  input: AddressInput,
): Promise<CustomerAddress | null> {
  const response = await apiClient.put<unknown>(
    `/account/addresses/${encodeURIComponent(addressId)}`,
    input,
  );
  return mapCustomerAddress(response.data);
}

export async function deleteCustomerAddress(addressId: string): Promise<void> {
  await apiClient.delete(`/account/addresses/${encodeURIComponent(addressId)}`);
}

export async function setDefaultCustomerAddress(
  addressId: string,
): Promise<CustomerAddress | null> {
  const response = await apiClient.patch<unknown>(
    `/account/addresses/${encodeURIComponent(addressId)}/default`,
    {},
  );
  return mapCustomerAddress(response.data);
}
