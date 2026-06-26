"use client";

import { useState } from "react";

interface WishlistButtonProps {
  className?: string;
}

export function WishlistButton({ className = "" }: WishlistButtonProps) {
  const [active, setActive] = useState(false);

  return (
    <button
      type="button"
      onClick={() => setActive((prev) => !prev)}
      aria-label={active ? "Remove from wishlist" : "Add to wishlist"}
      aria-pressed={active}
      className={`inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:border-[#c9a227]/40 hover:text-[#8b6914] ${active ? "border-[#c9a227]/50 text-[#c9a227]" : ""} ${className}`}
    >
      <svg
        className="h-5 w-5"
        fill={active ? "currentColor" : "none"}
        viewBox="0 0 24 24"
        stroke="currentColor"
        strokeWidth={1.5}
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
        />
      </svg>
    </button>
  );
}
