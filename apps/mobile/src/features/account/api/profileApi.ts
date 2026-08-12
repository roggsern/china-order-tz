import { apiClient } from '@/src/core/api';

export type CustomerProfile = {
  firstName: string | null;
  lastName: string | null;
  name: string | null;
  email: string | null;
  phone: string | null;
  emailVerifiedAt: string | null;
  pendingEmail: string | null;
  pendingEmailExpiresAt: string | null;
  updatedAt: string | null;
};

export type UpdateProfileInput = {
  first_name?: string;
  last_name?: string;
  phone?: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapCustomerProfile(raw: unknown): CustomerProfile | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  return {
    firstName: stringField(data, 'first_name'),
    lastName: stringField(data, 'last_name'),
    name: stringField(data, 'name'),
    email: stringField(data, 'email'),
    phone: stringField(data, 'phone'),
    emailVerifiedAt: stringField(data, 'email_verified_at'),
    pendingEmail: stringField(data, 'pending_email'),
    pendingEmailExpiresAt: stringField(data, 'pending_email_expires_at'),
    updatedAt: stringField(data, 'updated_at'),
  };
}

export async function fetchCustomerProfile(): Promise<CustomerProfile> {
  const response = await apiClient.get<unknown>('/profile');
  const profile = mapCustomerProfile(response.data);
  if (!profile) {
    throw new Error('Profile response was empty.');
  }
  return profile;
}

export async function updateCustomerProfile(
  input: UpdateProfileInput,
): Promise<CustomerProfile> {
  const response = await apiClient.patch<unknown>('/profile', input);
  const profile = mapCustomerProfile(response.data);
  if (!profile) {
    throw new Error('Updated profile response was empty.');
  }
  return profile;
}

/** Refresh auth-store user shape via GET /me after profile edits. */
export async function fetchCurrentUser(): Promise<unknown> {
  const response = await apiClient.get<unknown>('/me');
  return response.data;
}
