import type { Product } from "@/lib/types/catalog";
import { ProductCard } from "./ProductCard";

interface ProductGridProps {
  products: Product[];
  emptyMessage?: string;
}

export function ProductGrid({ products, emptyMessage = "No products found." }: ProductGridProps) {
  if (products.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 py-16 text-center">
        <span className="text-4xl">📦</span>
        <p className="mt-4 text-base font-medium text-zinc-600">{emptyMessage}</p>
        <p className="mt-1 text-sm text-zinc-400">Try adjusting your filters or search query.</p>
      </div>
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
