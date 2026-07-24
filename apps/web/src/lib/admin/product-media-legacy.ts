import type { AdminProductMedia } from "@/lib/api/admin-catalog";

export const LEGACY_MEDIA_HELPER_TEXT =
  "This image comes from the previous media system. Upload a new image to manage it in the catalog media system.";

export function isLegacyMediaItem(item: Pick<AdminProductMedia, "isLegacy">): boolean {
  return item.isLegacy === true;
}

export function supportsCatalogMediaActions(item: Pick<AdminProductMedia, "isLegacy">): boolean {
  return !isLegacyMediaItem(item);
}

export function hasLegacyMediaItems(items: ReadonlyArray<Pick<AdminProductMedia, "isLegacy">>): boolean {
  return items.some(isLegacyMediaItem);
}
