import type { AdminStoreRecord, AdminStoreWritePayload } from "@/lib/api/admin-stores";
import {
  canCreateStores,
  canUpdateStores,
  canViewStores,
} from "@/lib/api/admin-stores";

export { canCreateStores, canUpdateStores, canViewStores };

export type AdminStoreListItemView = {
  id: string;
  name: string;
  slug: string;
  code: string;
  logoUrl: string | null;
  isActive: boolean;
  statusLabel: string;
  createdAt: string | null;
};

export type AdminStoreFormValues = {
  name: string;
  slug: string;
  code: string;
  description: string;
  themeColor: string;
  isActive: boolean;
  storefrontEnabled: boolean;
  storefrontVisible: boolean;
  storefrontFeatured: boolean;
};

export function mapAdminStoreListItem(store: AdminStoreRecord): AdminStoreListItemView {
  return {
    id: store.id,
    name: store.name,
    slug: store.slug,
    code: store.code,
    logoUrl: store.logo_url,
    isActive: Boolean(store.is_active),
    statusLabel: store.is_active ? "Active" : "Inactive",
    createdAt: store.created_at,
  };
}

export function mapAdminStoreFormValues(store?: AdminStoreRecord | null): AdminStoreFormValues {
  return {
    name: store?.name ?? "",
    slug: store?.slug ?? "",
    code: store?.code ?? "",
    description: store?.description ?? "",
    themeColor: store?.theme_color ?? "#1F4B3A",
    isActive: store?.is_active ?? true,
    storefrontEnabled: store?.storefront_enabled ?? true,
    storefrontVisible: store?.storefront_visible ?? true,
    storefrontFeatured: store?.storefront_featured ?? false,
  };
}

export function toCreateStorePayload(values: AdminStoreFormValues): AdminStoreWritePayload & {
  code: string;
} {
  return {
    code: values.code.trim().toUpperCase(),
    name: values.name.trim(),
    slug: values.slug.trim() || undefined,
    description: values.description.trim() || null,
    theme_color: values.themeColor.trim() || null,
    is_active: values.isActive,
    storefront_enabled: values.storefrontEnabled,
    storefront_visible: values.storefrontVisible,
    storefront_featured: values.storefrontFeatured,
  };
}

export function toUpdateStorePayload(values: AdminStoreFormValues): AdminStoreWritePayload {
  return {
    name: values.name.trim(),
    slug: values.slug.trim() || undefined,
    description: values.description.trim() || null,
    theme_color: values.themeColor.trim() || null,
    is_active: values.isActive,
    storefront_enabled: values.storefrontEnabled,
    storefront_visible: values.storefrontVisible,
    storefront_featured: values.storefrontFeatured,
  };
}

export function brandingUploadReady(files: {
  logo?: File | null;
  banner?: File | null;
}): boolean {
  return Boolean(files.logo || files.banner);
}
