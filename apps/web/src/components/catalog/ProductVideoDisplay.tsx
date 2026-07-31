"use client";

import { useEffect, useState } from "react";
import type { ProductVideo } from "@/lib/types/catalog";
import {
  isSupportedProductVideoUrl,
  resolveProductVideoEmbedUrl,
  resolveProductVideoLabel,
  resolveProductVideoThumbnail,
} from "@/lib/catalog/product-video";

interface ProductVideoDisplayProps {
  video: ProductVideo;
  className?: string;
  /** Compact rail/thumbnail presentation without playback controls. */
  thumbnailOnly?: boolean;
}

export function ProductVideoDisplay({
  video,
  className = "",
  thumbnailOnly = false,
}: ProductVideoDisplayProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const embedUrl = resolveProductVideoEmbedUrl(video.url);
  const thumbnailUrl = resolveProductVideoThumbnail(video);
  const label = resolveProductVideoLabel(video);
  const isSupported = isSupportedProductVideoUrl(video.url) && Boolean(embedUrl);

  useEffect(() => {
    setIsPlaying(false);
  }, [video.id, video.url]);

  if (!isSupported || !embedUrl) {
    return (
      <div
        className={`flex items-center justify-center bg-gradient-to-br from-zinc-100 to-zinc-200 px-4 text-center ${className}`}
        role="img"
        aria-label={`${label} unavailable`}
      >
        <p className="text-sm font-medium text-zinc-500">Video unavailable</p>
      </div>
    );
  }

  if (isPlaying && !thumbnailOnly) {
    return (
      <div className={`relative overflow-hidden bg-black ${className}`}>
        <iframe
          src={`${embedUrl}${embedUrl.includes("?") ? "&" : "?"}autoplay=1`}
          title={label}
          className="h-full w-full border-0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowFullScreen
        />
      </div>
    );
  }

  return (
    <button
      type="button"
      onClick={() => {
        if (!thumbnailOnly) {
          setIsPlaying(true);
        }
      }}
      className={`group relative block h-full w-full overflow-hidden bg-zinc-900 ${className} ${
        thumbnailOnly ? "pointer-events-none cursor-default" : "cursor-pointer"
      }`}
      aria-label={thumbnailOnly ? `Video slide: ${label}` : `Play ${label}`}
      disabled={thumbnailOnly}
    >
      {thumbnailUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={thumbnailUrl} alt="" className="h-full w-full object-cover opacity-95" />
      ) : (
        <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-zinc-800 via-zinc-700 to-zinc-900">
          <span className="text-sm font-semibold text-white/80">Video</span>
        </div>
      )}

      {!thumbnailOnly ? (
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/25 transition group-hover:bg-black/35">
          <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white/95 text-zinc-900 shadow-[0_8px_30px_rgba(0,0,0,0.25)] transition group-hover:scale-105">
            <span className="ml-1 text-2xl leading-none" aria-hidden>
              ▶
            </span>
          </span>
        </span>
      ) : (
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/20">
          <span
            className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-[10px] text-zinc-900 shadow"
            aria-hidden
          >
            ▶
          </span>
        </span>
      )}
    </button>
  );
}
