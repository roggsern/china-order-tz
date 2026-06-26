"use client";

import Link from "next/link";
import { useCart } from "@/lib/cart/context";
import { CartEmptyState } from "./CartEmptyState";
import { CartItemRow } from "./CartItemRow";
import { OrderSummary } from "./OrderSummary";
import { SavedForLaterRow } from "./SavedForLaterRow";

export function CartPageContent() {
  const { items, savedForLater, totals, isHydrated } = useCart();

  if (!isHydrated) {
    return (
      <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 h-40 animate-pulse rounded-2xl bg-zinc-50" />
      </div>
    );
  }

  const hasItems = items.length > 0;
  const hasSaved = savedForLater.length > 0;

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-zinc-900">Shopping Cart</h1>
          <p className="mt-1 text-sm text-zinc-500">
            {hasItems
              ? `${totals.itemCount} item${totals.itemCount === 1 ? "" : "s"} in your cart`
              : "Review items before checkout"}
          </p>
        </div>
        {hasItems && (
          <Link
            href="/products"
            className="text-sm font-medium text-[#8b6914] transition hover:text-[#c9a227]"
          >
            Continue shopping
          </Link>
        )}
      </div>

      {!hasItems && !hasSaved ? (
        <div className="mt-10">
          <CartEmptyState />
        </div>
      ) : (
        <div className="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start lg:gap-10">
          <section aria-label="Cart items">
            {hasItems ? (
              <div className="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white px-5 shadow-sm sm:px-6">
                {items.map((item) => (
                  <CartItemRow key={item.id} item={item} />
                ))}
              </div>
            ) : (
              <CartEmptyState />
            )}

            {hasSaved && (
              <div className="mt-10">
                <h2 className="text-lg font-semibold text-zinc-900">Saved for later</h2>
                <p className="mt-1 text-sm text-zinc-500">
                  Items you saved will stay here until you move them back to your cart.
                </p>
                <div className="mt-4 rounded-3xl border border-zinc-100 bg-white px-5 sm:px-6">
                  {savedForLater.map((item) => (
                    <SavedForLaterRow key={item.id} item={item} />
                  ))}
                </div>
              </div>
            )}
          </section>

          {hasItems && <OrderSummary totals={totals} />}
        </div>
      )}
    </div>
  );
}
