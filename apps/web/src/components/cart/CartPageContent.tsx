"use client";

import Link from "next/link";
import { AnimatePresence } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { CartEmptyState } from "./CartEmptyState";
import { CartItemRow } from "./CartItemRow";
import { CartFrequentlyBoughtTogether } from "./CartFrequentlyBoughtTogether";
import { CartMobileStickyCheckout } from "./CartMobileStickyCheckout";
import { OrderSummary } from "./OrderSummary";
import { SavedForLaterRow } from "./SavedForLaterRow";
import { CartPageSkeleton } from "@/components/ui/PageSkeletons";

export function CartPageContent() {
  const { items, savedForLater, totals, isHydrated } = useCart();

  if (!isHydrated) {
    return <CartPageSkeleton />;
  }

  const hasItems = items.length > 0;
  const hasSaved = savedForLater.length > 0;

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 pb-32 sm:px-6 lg:px-8 lg:pb-10">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Shopping Cart
          </p>
          <h1 className="mt-1 text-3xl font-bold tracking-tight text-zinc-900">Your Cart</h1>
          <p className="mt-1 text-sm text-zinc-500">
            {hasItems
              ? `${totals.itemCount} item${totals.itemCount === 1 ? "" : "s"} · continues from your product choices`
              : "Review items before checkout"}
          </p>
        </div>
        {hasItems && (
          <Link
            href="/products"
            className="text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
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
        <>
          <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)] lg:items-start lg:gap-8">
            <section aria-label="Cart items" className="min-w-0 space-y-4">
              {hasItems ? (
                <AnimatePresence initial={false}>
                  {items.map((item) => (
                    <CartItemRow key={item.id} item={item} />
                  ))}
                </AnimatePresence>
              ) : (
                <CartEmptyState />
              )}

              {hasSaved && (
                <div className="mt-10">
                  <h2 className="text-lg font-semibold text-zinc-900">Saved for later</h2>
                  <p className="mt-1 text-sm text-zinc-500">
                    Items you saved will stay here until you move them back to your cart.
                  </p>
                  <div className="mt-4 space-y-3 rounded-2xl border border-zinc-100 bg-white p-4 sm:p-5">
                    {savedForLater.map((item) => (
                      <SavedForLaterRow key={item.id} item={item} />
                    ))}
                  </div>
                </div>
              )}
            </section>

            {hasItems && (
              <div className="hidden lg:block">
                <OrderSummary totals={totals} />
              </div>
            )}
          </div>

          {hasItems ? <CartFrequentlyBoughtTogether /> : null}

          {hasItems && (
            <div className="mt-8 lg:hidden">
              <OrderSummary totals={totals} showCheckoutAction={false} />
            </div>
          )}
        </>
      )}

      <CartMobileStickyCheckout />
    </div>
  );
}
