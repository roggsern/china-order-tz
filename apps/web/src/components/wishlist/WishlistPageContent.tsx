"use client";

import Link from "next/link";
import { formatPrice } from "@/lib/catalog/utils";
import { EmptyState } from "@/components/ui/EmptyState";
import { WishlistPageSkeleton } from "@/components/ui/PageSkeletons";
import { useWishlist } from "@/lib/wishlist/use-wishlist";
import { removeWishlistItem } from "@/lib/wishlist/storage";
import { showWishlistToast } from "@/lib/customer/customer-toast";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";

export function WishlistPageContent() {
  const { items, ready } = useWishlist();

  if (!ready) {
    return <WishlistPageSkeleton />;
  }

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <EmptyState
          icon="❤️"
          title="Wishlist empty"
          description="Save products you love."
          primaryAction={{ label: "Browse Products", href: "/products" }}
          secondaryAction={{ label: "Browse categories", href: "/categories" }}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <header>
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Saved</p>
        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
          Wishlist
        </h1>
        <p className="mt-2 text-sm text-zinc-500">
          {items.length} saved item{items.length === 1 ? "" : "s"}
        </p>
      </header>

      <ul className="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
        {items.map((item) => (
          <li
            key={item.productId}
            className="group overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_2px_16px_rgba(0,0,0,0.04)] transition hover:-translate-y-0.5 hover:border-[#c9a227]/30 hover:shadow-[0_8px_28px_rgba(201,162,39,0.12)]"
          >
            <Link href={`/products/${item.slug}`} className="block">
              <ProductImageDisplay
                src={item.imageUrl}
                image={{
                  id: item.productId,
                  emoji: item.emoji ?? "✨",
                  gradient: item.gradient ?? "from-zinc-100 to-zinc-200",
                  alt: item.name,
                  url: item.imageUrl,
                }}
                fallbackEmoji={item.emoji ?? "✨"}
                fallbackGradient={item.gradient ?? "from-zinc-100 to-zinc-200"}
                className="aspect-square w-full"
                emojiClassName="text-4xl"
              />
              <div className="p-3 sm:p-4">
                <p className="line-clamp-2 text-sm font-semibold text-zinc-900 group-hover:text-[#8b6914]">
                  {item.name}
                </p>
                {item.price != null ? (
                  <p className="mt-1.5 text-sm font-bold tabular-nums text-zinc-900">
                    {formatPrice(item.price)}
                  </p>
                ) : null}
              </div>
            </Link>
            <div className="border-t border-zinc-100 px-3 pb-3 sm:px-4 sm:pb-4">
              <button
                type="button"
                onClick={() => {
                  removeWishlistItem(item.productId);
                  showWishlistToast(false);
                }}
                className="mt-2 w-full rounded-xl border border-zinc-200 py-2 text-xs font-semibold text-zinc-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"
              >
                Remove
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
