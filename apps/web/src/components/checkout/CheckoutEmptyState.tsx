"use client";

import Link from "next/link";
import { EmptyState } from "@/components/ui/EmptyState";

export function CheckoutEmptyState() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
      <EmptyState
        icon="🛒"
        title="Nothing to check out yet"
        description="Add products to your cart, choose configurations, and come back when you're ready to complete a secure order."
        primaryAction={{ label: "Continue Shopping", href: "/products" }}
        secondaryAction={{ label: "View cart", href: "/cart" }}
      />
      <p className="mt-6 text-center text-sm text-zinc-500">
        <Link href="/wishlist" className="font-semibold text-[#8b6914] hover:text-[#c9a227]">
          Or browse your wishlist →
        </Link>
      </p>
    </div>
  );
}
