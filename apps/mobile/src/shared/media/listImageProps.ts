/** Shared Expo Image cache for list/card thumbnails (SDK 57). */
export const LIST_IMAGE_CACHE_POLICY = 'memory-disk' as const;

export function listImageProps(uri: string): {
  cachePolicy: typeof LIST_IMAGE_CACHE_POLICY;
  recyclingKey: string;
} {
  return {
    cachePolicy: LIST_IMAGE_CACHE_POLICY,
    recyclingKey: uri,
  };
}
