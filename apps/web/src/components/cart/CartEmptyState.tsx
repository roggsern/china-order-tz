"use client";

import { CartRecommendedProducts } from "./CartRecommendedProducts";
import { EmptyState } from "@/components/ui/EmptyState";

export function CartEmptyState() {
  return (
    <div className="text-center">
      <EmptyState
        icon="🛒"
        title="Your cart is waiting"
        description="Discover amazing products and start shopping."
        primaryAction={{ label: "Continue Shopping", href: "/products" }}
        secondaryAction={{ label: "Browse categories", href: "/categories" }}
      />
      <CartRecommendedProducts />
    </div>
  );
}
