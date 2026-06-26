import Link from "next/link";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { pickProductShippingContext, type Product } from "@/lib/types/catalog";
import { calculateDiscount } from "@/lib/catalog/utils";
import { PriceDisplay } from "./PriceDisplay";
import { RatingStars } from "./RatingStars";
import { AddToCartButton } from "./AddToCartButton";
import { ProductBadges } from "./ProductBadge";
import { ProductImageDisplay } from "./ProductImageDisplay";
import { ProductCardFooter } from "./ProductCardFooter";
import { StockStatus } from "./StockStatus";
import { TrustBadges } from "./TrustBadges";

interface ProductCardProps {
  product: Product;
  variant?: "default" | "luxury";
}

export function ProductCard({ product, variant = "default" }: ProductCardProps) {
  const discount = calculateDiscount(product.price, product.oldPrice);
  const primaryImage = getProductPrimaryImage(product);
  const isLuxury = variant === "luxury";

  return (
    <article
      className={`group flex flex-col overflow-hidden bg-white transition duration-500 ease-out hover:-translate-y-1 ${
        isLuxury
          ? "rounded-3xl border border-zinc-100/80 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:border-[#c9a227]/25 hover:shadow-[0_20px_48px_rgba(0,0,0,0.12)]"
          : "rounded-2xl border border-zinc-100 shadow-[0_2px_12px_rgba(0,0,0,0.04)] hover:border-zinc-200 hover:shadow-[0_16px_40px_rgba(0,0,0,0.1)]"
      }`}
    >
      <Link href={`/products/${product.slug}`} className="relative block overflow-hidden">
        <ProductImageDisplay
          image={primaryImage}
          fallbackEmoji={product.emoji}
          fallbackGradient={product.gradient}
          className={`aspect-square ${isLuxury ? "sm:aspect-[4/5]" : ""}`}
          emojiClassName={isLuxury ? "text-7xl drop-shadow-lg" : "text-6xl drop-shadow-md"}
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/15 via-transparent to-transparent opacity-0 transition duration-500 group-hover:opacity-100" />

        <div className="absolute left-3 top-3 z-10 sm:left-4 sm:top-4">
          <ProductBadges badges={product.badges} />
        </div>

        {discount > 0 && (
          <span className="absolute right-3 top-3 z-10 rounded-full bg-[#c9a227] px-2.5 py-1 text-[10px] font-bold text-zinc-900 shadow-sm sm:right-4 sm:top-4">
            -{discount}%
          </span>
        )}
      </Link>

      <div className={`flex flex-1 flex-col ${isLuxury ? "p-5 sm:p-6" : "p-4"}`}>
        {product.brand && (
          <p className="text-[10px] font-semibold uppercase tracking-[0.15em] text-[#c9a227]">
            {product.brand}
          </p>
        )}

        <Link href={`/products/${product.slug}`}>
          <h3
            className={`line-clamp-2 font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914] ${
              isLuxury ? "mt-1 text-base" : "text-sm"
            }`}
          >
            {product.name}
          </h3>
        </Link>

        <div className="mt-2.5">
          <RatingStars
            rating={product.rating}
            size="sm"
            showValue
            reviewCount={product.reviews}
          />
        </div>

        <div className="mt-3">
          <PriceDisplay
            price={product.price}
            oldPrice={product.oldPrice}
            size={isLuxury ? "md" : "sm"}
            showDiscount={false}
          />
        </div>

        <div className="mt-3">
          <StockStatus stock={product.stock} size="sm" />
        </div>

        {product.trustBadges.length > 0 && (
          <TrustBadges badges={product.trustBadges.slice(0, 2)} size="sm" className="mt-3" />
        )}

        <div className="mt-4">
          <AddToCartButton
            product={product}
            disabled={product.stock <= 0}
            className={
              isLuxury
                ? "rounded-full py-3 text-sm font-semibold uppercase tracking-wide"
                : undefined
            }
          />
        </div>
      </div>

      <ProductCardFooter {...pickProductShippingContext(product)} />
    </article>
  );
}
