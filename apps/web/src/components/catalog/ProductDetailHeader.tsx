import type { Category } from "@/lib/types/catalog";
import type { Product } from "@/lib/types/catalog";
import { ProductBadges } from "./ProductBadge";
import { RatingStars } from "./RatingStars";
import { StockStatus } from "./StockStatus";
import { ProductOriginBadge } from "./ProductOriginBadge";

interface ProductDetailHeaderProps {
  product: Product;
  category?: Category;
  className?: string;
}

function formatSku(product: Product): string {
  if (product.sku?.trim()) return product.sku.trim();
  return `SKU-${String(product.id).padStart(5, "0")}`;
}

export function ProductDetailHeader({ product, category, className = "" }: ProductDetailHeaderProps) {
  return (
    <header className={className}>
      <div className="flex flex-wrap items-center gap-2">
        <ProductBadges badges={product.badges} />
        {category && (
          <span className="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
            {category.icon} {category.name}
          </span>
        )}
      </div>

      {product.brand && (
        <p className="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
          {product.brand}
        </p>
      )}

      <h1 className="mt-1.5 text-xl font-bold leading-tight tracking-tight text-zinc-900 sm:text-2xl">
        {product.name}
      </h1>

      <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500">
        {product.brand && (
          <span>
            Brand: <span className="font-medium text-zinc-700">{product.brand}</span>
          </span>
        )}
        {category && (
          <span>
            Category: <span className="font-medium text-zinc-700">{category.name}</span>
          </span>
        )}
        <span>
          SKU: <span className="font-mono text-xs font-medium text-zinc-700">{formatSku(product)}</span>
        </span>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <StockStatus stock={product.stock} size="md" />
        <ProductOriginBadge origin={product.origin} size="md" />
      </div>

      <div className="mt-4">
        <RatingStars rating={product.rating} size="md" showValue reviewCount={product.reviews} />
      </div>
    </header>
  );
}
