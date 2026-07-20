import { ProductGridSkeleton } from "@/components/catalog/ProductGridSkeleton";
import { ProductFiltersSkeleton } from "@/components/catalog/ProductFiltersSkeleton";

export default function ProductsLoading() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div className="mb-6 h-8 w-48 skeleton-shimmer rounded-lg" aria-hidden />
      <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
        <ProductFiltersSkeleton />
        <ProductGridSkeleton count={8} />
      </div>
    </div>
  );
}
