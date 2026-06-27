import Link from "next/link";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { pickProductShippingContext, type Product } from "@/lib/types/catalog";
import { calculateDiscount } from "@/lib/catalog/utils";
import { PriceDisplay } from "./PriceDisplay";
import { RatingStars } from "./RatingStars";
import { AddToCartButton } from "./AddToCartButton";
import { ProductCardBadges } from "./ProductCardBadges";
import { ProductImageDisplay } from "./ProductImageDisplay";
import { ProductCardFooter } from "./ProductCardFooter";
import { WishlistButton } from "./WishlistButton";

interface ProductCardProps {
  product: Product;
  variant?: "default" | "luxury";
}

export function ProductCard({ product, variant = "default" }: ProductCardProps) {
  const discount = calculateDiscount(product.price, product.oldPrice);
  const primaryImage = getProductPrimaryImage(product);
  const isLuxury = variant === "luxury";
  const outOfStock = product.stock <= 0;

  return (
    <article
      className={`group flex h-full flex-col overflow-hidden bg-white transition duration-300 ease-out active:scale-[0.99] md:active:scale-100 md:hover:-translate-y-1 ${
        isLuxury
          ? "rounded-2xl border border-zinc-100/90 shadow-[0_2px_16px_rgba(0,0,0,0.05)] md:rounded-3xl md:shadow-[0_4px_24px_rgba(0,0,0,0.06)] md:hover:border-[#c9a227]/25 md:hover:shadow-[0_16px_40px_rgba(201,162,39,0.12)]"
          : "rounded-2xl border border-zinc-100 shadow-[0_2px_12px_rgba(0,0,0,0.04)] md:hover:border-zinc-200 md:hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]"
      }`}
    >
      <div className="relative">
        <Link
          href={`/products/${product.slug}`}
          className="relative block overflow-hidden rounded-t-2xl md:rounded-t-3xl"
        >
          <ProductImageDisplay
            image={primaryImage}
            fallbackEmoji={product.emoji}
            fallbackGradient={product.gradient}
            className="aspect-[4/5] w-full sm:aspect-square"
            emojiClassName={isLuxury ? "text-6xl drop-shadow-lg sm:text-7xl" : "text-5xl drop-shadow-md sm:text-6xl"}
          />
          <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-60 transition duration-300 md:opacity-0 md:group-hover:opacity-100" />
        </Link>

        <div className="absolute left-2 top-2 z-10 sm:left-3 sm:top-3">
          <ProductCardBadges badges={product.badges} discount={discount} />
        </div>

        {discount > 0 && (
          <span className="absolute right-2 top-2 z-10 rounded-full bg-zinc-900 px-2 py-0.5 text-[10px] font-bold text-[#e8c547] shadow-sm sm:right-3 sm:top-3 sm:px-2.5 sm:py-1">
            -{discount}%
          </span>
        )}

        <div className="absolute bottom-2 right-2 z-10 sm:bottom-3 sm:right-3 md:opacity-100 md:transition md:duration-300 md:group-hover:opacity-100">
          <WishlistButton size="sm" className="md:shadow-md" />
        </div>

        {outOfStock && (
          <div className="absolute inset-x-0 bottom-0 z-10 bg-zinc-900/75 px-3 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm sm:text-xs">
            Out of stock
          </div>
        )}
      </div>

      <div className={`flex flex-1 flex-col ${isLuxury ? "p-3 sm:p-4 md:p-5" : "p-3 sm:p-4"}`}>
        {product.brand && (
          <p className="truncate text-[9px] font-bold uppercase tracking-[0.14em] text-[#c9a227] sm:text-[10px]">
            {product.brand}
          </p>
        )}

        <Link href={`/products/${product.slug}`} className="mt-1 block min-h-[2.5rem] sm:min-h-[2.75rem]">
          <h3
            className={`line-clamp-2 font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914] ${
              isLuxury ? "text-sm sm:text-base" : "text-sm"
            }`}
          >
            {product.name}
          </h3>
        </Link>

        <div className="mt-2">
          <RatingStars
            rating={product.rating}
            size="sm"
            showValue
            reviewCount={product.reviews}
            compactReviews
          />
        </div>

        <div className="mt-2.5 sm:mt-3">
          <PriceDisplay
            price={product.price}
            oldPrice={product.oldPrice}
            size={isLuxury ? "md" : "sm"}
            showDiscount
            variant="premium"
          />
        </div>

        <div className="mt-auto flex items-center gap-2 pt-3 sm:pt-4">
          <AddToCartButton
            product={product}
            disabled={outOfStock}
            variant="card"
            className="min-w-0 flex-1"
          />
        </div>
      </div>

      <ProductCardFooter
        {...pickProductShippingContext(product)}
        className="rounded-b-2xl md:rounded-b-3xl"
      />
    </article>
  );
}
