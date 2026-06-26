"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useCart } from "@/lib/cart/context";

/** Legacy route — redirects to the main checkout page. */
export function CheckoutSummaryContent() {
  const router = useRouter();
  const { items, isHydrated } = useCart();

  useEffect(() => {
    if (!isHydrated) {
      return;
    }

    router.replace(items.length === 0 ? "/cart" : "/checkout");
  }, [isHydrated, items.length, router]);

  return (
    <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
      <div className="h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
      <div className="mt-8 h-96 animate-pulse rounded-3xl bg-zinc-50" />
    </div>
  );
}
