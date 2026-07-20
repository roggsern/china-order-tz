"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import { useWishlist } from "@/lib/wishlist/use-wishlist";

interface WishlistButtonProps {
  className?: string;
  size?: "sm" | "md";
  productId?: number;
  slug?: string;
  name?: string;
  imageUrl?: string;
  emoji?: string;
  gradient?: string;
  price?: number;
}

const sizeClasses = {
  sm: "h-9 w-9",
  md: "h-11 w-11",
};

const iconSizes = {
  sm: "h-4 w-4",
  md: "h-5 w-5",
};

export function WishlistButton({
  className = "",
  size = "md",
  productId,
  slug,
  name,
  imageUrl,
  emoji,
  gradient,
  price,
}: WishlistButtonProps) {
  const { isLoggedIn, isReady } = useCustomerSession();
  const pathname = usePathname();
  const { isSaved, toggle, ready } = useWishlist();
  const [showInvite, setShowInvite] = useState(false);
  const [active, setActive] = useState(false);

  useEffect(() => {
    if (ready && productId != null) {
      setActive(isSaved(productId));
    }
  }, [ready, productId, isSaved]);

  return (
    <div className="relative">
      <button
        type="button"
        onClick={(event) => {
          event.preventDefault();
          event.stopPropagation();

          // Guests may still save locally — invite only if they explicitly need account features.
          // Prefer local wishlist for polish; invite when no product context (header/account).
          if (productId == null || !slug || !name) {
            if (isReady && !isLoggedIn) {
              setShowInvite(true);
              return;
            }
            return;
          }

          const added = toggle({
            productId,
            slug,
            name,
            imageUrl,
            emoji,
            gradient,
            price,
          });
          setActive(added);
        }}
        aria-label={active ? "Remove from wishlist" : "Add to wishlist"}
        aria-pressed={active}
        className={`inline-flex shrink-0 items-center justify-center rounded-full border bg-white/95 text-zinc-500 shadow-[0_2px_10px_rgba(0,0,0,0.12)] backdrop-blur-sm transition-all duration-200 ease-out hover:scale-105 hover:border-[#c9a227]/45 hover:text-[#c9a227] hover:shadow-[0_4px_14px_rgba(201,162,39,0.2)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c9a227] active:scale-95 ${
          active
            ? "border-[#c9a227]/50 bg-[#c9a227]/10 text-[#c9a227]"
            : "border-white/80"
        } ${sizeClasses[size]} ${className}`}
      >
        <svg
          className={`${iconSizes[size]} transition-transform duration-200 ease-out ${
            active ? "scale-110" : ""
          }`}
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

      {showInvite ? (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center"
          role="dialog"
          aria-modal="true"
          aria-label="Sign in to save wishlist"
          onClick={() => setShowInvite(false)}
        >
          <div
            className="w-full max-w-md animate-fade-in"
            onClick={(event) => event.stopPropagation()}
          >
            <AuthInvitationCard
              context="wishlist"
              returnUrl={pathname || "/wishlist"}
              compact
            />
            <button
              type="button"
              onClick={() => setShowInvite(false)}
              className="mt-3 w-full text-center text-sm font-semibold text-white/90 transition hover:text-white"
            >
              Not now
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
