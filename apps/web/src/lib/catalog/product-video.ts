import type { ProductVideo } from "@/lib/types/catalog";

const YOUTUBE_ID_PATTERN =
  /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{6,})/i;
const VIMEO_ID_PATTERN = /(?:vimeo\.com\/)(\d+)/i;

export function youtubeVideoId(url: string): string | null {
  const match = url.trim().match(YOUTUBE_ID_PATTERN);
  return match?.[1] ?? null;
}

export function vimeoVideoId(url: string): string | null {
  const match = url.trim().match(VIMEO_ID_PATTERN);
  return match?.[1] ?? null;
}

export function isSupportedProductVideoUrl(url: string | null | undefined): boolean {
  if (!url?.trim()) {
    return false;
  }

  return youtubeVideoId(url) !== null || vimeoVideoId(url) !== null;
}

export function resolveProductVideoEmbedUrl(url: string | null | undefined): string | null {
  if (!url?.trim()) {
    return null;
  }

  const youtubeId = youtubeVideoId(url);
  if (youtubeId) {
    return `https://www.youtube.com/embed/${youtubeId}?rel=0&modestbranding=1`;
  }

  const vimeoId = vimeoVideoId(url);
  if (vimeoId) {
    return `https://player.vimeo.com/video/${vimeoId}`;
  }

  return null;
}

export function resolveProductVideoThumbnail(
  video: Pick<ProductVideo, "url" | "thumbnail_url">,
): string | null {
  const provided = video.thumbnail_url?.trim();
  if (provided) {
    return provided;
  }

  const youtubeId = youtubeVideoId(video.url);
  if (youtubeId) {
    return `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg`;
  }

  return null;
}

export function resolveProductVideoLabel(
  video: Pick<ProductVideo, "title" | "alt_text">,
  fallback = "Product video",
): string {
  return video.title?.trim() || video.alt_text?.trim() || fallback;
}
