import Link from "next/link";
import type { Product } from "@/lib/types/catalog";
import { calculateDiscount } from "@/lib/catalog/utils";
import { RatingStars } from "./RatingStars";
import { PriceDisplay } from "./PriceDisplay";
import { AddToCartButton } from "./AddToCartButton";

interface ProductCardProps {
  product: Product;
}

export function ProductCard({ product }: ProductCardProps) {
  const discount = calculateDiscount(product.price, product.oldPrice);

  return (
    <article className="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-zinc-200 hover:shadow-xl">
      <Link href={`/products/${product.slug}`} className="relative block">
        <div
          className={`flex aspect-square items-center justify-center bg-gradient-to-br ${product.gradient}`}
        >
          <span className="text-6xl drop-shadow-md transition group-hover:scale-110">
            {product.emoji}
          </span>
        </div>
        <span className="absolute left-3 top-3 rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
          {product.badge}
        </span>
        {discount > 0 && (
          <span className="absolute right-3 top-3 rounded-full bg-zinc-900/80 px-2 py-1 text-[10px] font-bold text-[#e8c547] backdrop-blur">
            -{discount}%
          </span>
        )}
      </Link>

      <div className="flex flex-1 flex-col p-4">
        <Link href={`/products/${product.slug}`}>
          <h3 className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914]">
            {product.name}
          </h3>
        </Link>

        <div className="mt-2 flex items-center gap-2">
          <RatingStars rating={product.rating} />
          <span className="text-xs text-zinc-400">
            {product.rating} ({product.reviews.toLocaleString()})
          </span>
        </div>

        <div className="mt-3">
          <PriceDisplay price={product.price} oldPrice={product.oldPrice} size="sm" showDiscount={false} />
        </div>

        <div className="mt-4">
          <AddToCartButton disabled={product.stock <= 0} />
        </div>
      </div>
    </article>
  );
}
