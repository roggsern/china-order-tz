import Link from "next/link";
import { getCatalogProductImageSrc } from "@/lib/catalog/product-images";
import { pickProductShippingContext, type Product } from "@/lib/types/catalog";
import { calculateDiscount } from "@/lib/catalog/utils";
import { PriceDisplay } from "./PriceDisplay";
import { FeatureGatedRatingStars } from "./FeatureGatedRatingStars";
import { AddToCartButton } from "./AddToCartButton";
import { ProductCardBadges } from "./ProductCardBadges";
import { ProductConditionBadge } from "./ProductConditionBadge";
import { ProductImageDisplay } from "./ProductImageDisplay";
import { ProductCardFooter } from "./ProductCardFooter";
import { WishlistButton } from "./WishlistButton";
import {
  isProductCardPurchaseDisabled,
  isProductPurchaseUnavailable,
  resolveProductCardAvailabilityOverlay,
} from "@/lib/catalog/product-availability";

interface ProductCardProps {
  product: Product;
  variant?: "default" | "luxury";
}

export function ProductCard({ product, variant = "default" }: ProductCardProps) {
  const discount = calculateDiscount(product.price, product.oldPrice);
  const imageSrc = getCatalogProductImageSrc(product);
  const isLuxury = variant === "luxury";
  const purchaseUnavailable = isProductPurchaseUnavailable(product);
  const cardPurchaseDisabled = isProductCardPurchaseDisabled(product);
  const availabilityOverlay = resolveProductCardAvailabilityOverlay(product);

  return (
    <article
      className={`group flex h-full flex-col overflow-hidden bg-white transition-all duration-300 ease-out active:scale-[0.99] md:active:scale-100 md:hover:-translate-y-1.5 ${
        isLuxury
          ? "rounded-2xl border border-zinc-100/90 shadow-[0_2px_16px_rgba(0,0,0,0.05)] md:rounded-2xl md:shadow-[0_4px_20px_rgba(0,0,0,0.06)] md:hover:border-[#c9a227]/30 md:hover:shadow-[0_18px_44px_rgba(201,162,39,0.14)]"
          : "rounded-2xl border border-zinc-100/90 shadow-[0_2px_12px_rgba(0,0,0,0.04)] md:hover:border-zinc-200/90 md:hover:shadow-[0_14px_36px_rgba(0,0,0,0.09)]"
      }`}
    >
      <div className="relative">
        <Link
          href={`/products/${product.slug}`}
          className="relative block overflow-hidden bg-zinc-100"
        >
          <ProductImageDisplay
            src={imageSrc}
            product={product}
            fallbackEmoji={product.emoji}
            fallbackGradient={product.gradient}
            className="aspect-square w-full"
            emojiClassName={isLuxury ? "text-6xl drop-shadow-lg sm:text-7xl" : "text-5xl drop-shadow-md sm:text-6xl"}
          />
          <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent opacity-70 transition-opacity duration-300 md:opacity-0 md:group-hover:opacity-100" />
        </Link>

        <div className="absolute left-2.5 top-2.5 z-10 flex flex-col gap-1 sm:left-3 sm:top-3">
          <ProductConditionBadge
            condition={product.productCondition}
            label={product.productConditionLabel}
          />
          <ProductCardBadges badges={product.badges} discount={discount} />
        </div>

        <div className="absolute right-2.5 top-2.5 z-10 sm:right-3 sm:top-3">
          <WishlistButton
            size="sm"
            productId={product.id}
            catalogProductId={product.catalogProductId}
            slug={product.slug}
            name={product.name}
            imageUrl={imageSrc}
            emoji={product.emoji}
            gradient={product.gradient}
            price={product.price}
          />
        </div>

        {availabilityOverlay ? (
          <div className="absolute inset-x-0 bottom-0 z-10 bg-zinc-900/80 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-[0.08em] text-white backdrop-blur-sm sm:text-[11px]">
            {availabilityOverlay}
          </div>
        ) : null}
      </div>

      <div className={`flex flex-1 flex-col ${isLuxury ? "p-3.5 sm:p-4 md:p-5" : "p-3.5 sm:p-4"}`}>
        {product.brand && (
          <p className="truncate text-[10px] font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            {product.brand}
          </p>
        )}

        <Link href={`/products/${product.slug}`} className="mt-1.5 block">
          <h3
            className={`line-clamp-2 min-h-[2.5rem] font-semibold leading-snug text-zinc-900 transition-colors duration-200 hover:text-[#8b6914] sm:min-h-[2.75rem] ${
              isLuxury ? "text-sm sm:text-[15px]" : "text-sm"
            }`}
          >
            {product.name}
          </h3>
        </Link>

        <div className="mt-2">
          <FeatureGatedRatingStars
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
            showDiscount={false}
            variant="premium"
          />
        </div>

        <div className="mt-auto pt-3 sm:pt-4">
          <AddToCartButton
            product={product}
            disabled={cardPurchaseDisabled}
            purchaseUnavailable={purchaseUnavailable}
            availabilityStatus={product.availabilityStatus}
            variant="card"
            className="w-full"
          />
        </div>
      </div>

      <ProductCardFooter
        {...pickProductShippingContext(product)}
        className="rounded-b-2xl"
      />
    </article>
  );
}
