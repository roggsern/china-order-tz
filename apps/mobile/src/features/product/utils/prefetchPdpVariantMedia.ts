import { Image } from 'expo-image';
import { PDP_GALLERY_CACHE_POLICY } from './pdpVariantMedia';

/**
 * Prefetch PDP variant frames into Expo Image memory+disk cache.
 * Empty input is a successful no-op. Failures resolve false — callers keep
 * currently displayed media instead of blanking the gallery.
 */
export async function prefetchPdpVariantMedia(urls: string[]): Promise<boolean> {
  const unique = [...new Set(urls.map((url) => url.trim()).filter(Boolean))];
  if (unique.length === 0) return true;
  try {
    return await Image.prefetch(unique, PDP_GALLERY_CACHE_POLICY);
  } catch {
    return false;
  }
}
