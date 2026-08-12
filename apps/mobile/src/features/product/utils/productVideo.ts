/**
 * Product video URL helpers — mirrors web `product-video.ts` + API ProductMediaUrl.
 * Never treat unsupported URLs as playable; never inject HTML.
 */

const YOUTUBE_ID_PATTERN =
  /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)([A-Za-z0-9_-]{6,})/i;
const VIMEO_ID_PATTERN = /(?:vimeo\.com\/)(\d+)/i;

export type ProductVideoProvider = 'youtube' | 'vimeo';

export function youtubeVideoId(url: string): string | null {
  const match = url.trim().match(YOUTUBE_ID_PATTERN);
  return match?.[1] ?? null;
}

export function vimeoVideoId(url: string): string | null {
  const match = url.trim().match(VIMEO_ID_PATTERN);
  return match?.[1] ?? null;
}

export function resolveProductVideoProvider(
  url: string | null | undefined,
): ProductVideoProvider | null {
  if (!url?.trim()) return null;
  if (youtubeVideoId(url)) return 'youtube';
  if (vimeoVideoId(url)) return 'vimeo';
  return null;
}

export function isSupportedProductVideoUrl(
  url: string | null | undefined,
): boolean {
  return resolveProductVideoProvider(url) !== null;
}

/**
 * Safe embed URL constructed only from validated provider IDs.
 * YouTube uses nocookie host + playsinline for WebView embeds.
 */
export function resolveProductVideoEmbedUrl(
  url: string | null | undefined,
  options?: { origin?: string | null },
): string | null {
  if (!url?.trim()) return null;

  const youtubeId = youtubeVideoId(url);
  if (youtubeId) {
    const params = new URLSearchParams({
      rel: '0',
      modestbranding: '1',
      playsinline: '1',
    });
    const origin = options?.origin?.trim();
    if (origin) {
      params.set('origin', origin.replace(/\/$/, ''));
    }
    return `https://www.youtube-nocookie.com/embed/${youtubeId}?${params.toString()}`;
  }

  const vimeoId = vimeoVideoId(url);
  if (vimeoId) {
    return `https://player.vimeo.com/video/${vimeoId}?playsinline=1`;
  }

  return null;
}

/** Canonical watch/open URL for secondary fallback (never invent). */
export function resolveProductVideoExternalUrl(
  url: string | null | undefined,
): string | null {
  if (!url?.trim()) return null;
  const youtubeId = youtubeVideoId(url);
  if (youtubeId) {
    return `https://www.youtube.com/watch?v=${youtubeId}`;
  }
  const vimeoId = vimeoVideoId(url);
  if (vimeoId) {
    return `https://vimeo.com/${vimeoId}`;
  }
  return null;
}

/**
 * HTML document for WebView `source={{ html, baseUrl }}`.
 * baseUrl/Referer identity is required to avoid YouTube Error 153 on Android.
 */
export function buildProductVideoEmbedHtml(input: {
  embedUrl: string;
  title?: string | null;
}): string {
  const title = (input.title?.trim() || 'Product video').replace(/[<>&"]/g, '');
  const src = input.embedUrl.replace(/"/g, '&quot;');
  return `<!DOCTYPE html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/><style>html,body{margin:0;padding:0;height:100%;background:#000;overflow:hidden}iframe{position:absolute;inset:0;width:100%;height:100%;border:0}</style></head><body><iframe src="${src}" title="${title}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></body></html>`;
}

export function resolveProductVideoThumbnailUrl(input: {
  url: string;
  thumbnailUrl?: string | null;
}): string | null {
  const provided = input.thumbnailUrl?.trim();
  if (provided) return provided;

  const youtubeId = youtubeVideoId(input.url);
  if (youtubeId) {
    return `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg`;
  }

  return null;
}

export function resolveProductVideoLabel(input: {
  title?: string | null;
  altText?: string | null;
  fallback?: string;
}): string {
  return (
    input.title?.trim() ||
    input.altText?.trim() ||
    input.fallback ||
    'Product video'
  );
}
