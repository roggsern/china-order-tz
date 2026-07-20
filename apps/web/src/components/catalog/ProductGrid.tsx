import { EmptyState } from "@/components/ui/EmptyState";
import type { Product } from "@/lib/types/catalog";
import { ProductCard } from "./ProductCard";

interface ProductGridProps {
  products: Product[];
  emptyMessage?: string;
  emptyTitle?: string;
  searchQuery?: string;
}

export function ProductGrid({
  products,
  emptyMessage = "Try different keywords or browse categories.",
  emptyTitle = "No matching products found",
  searchQuery,
}: ProductGridProps) {
  if (products.length === 0) {
    return (
      <EmptyState
        tone="search"
        icon="🔍"
        title={emptyTitle}
        description={
          searchQuery?.trim()
            ? `No products match “${searchQuery.trim()}”. ${emptyMessage}`
            : emptyMessage
        }
        primaryAction={{ label: "Browse Products", href: "/products" }}
        secondaryAction={{ label: "Browse categories", href: "/categories" }}
      />
    );
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 lg:gap-6 xl:grid-cols-4">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}
