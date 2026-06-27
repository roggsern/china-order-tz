import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProductBySlug, getRelatedProducts } from "@/lib/catalog/products";
import { pickProductShippingContext } from "@/lib/types/catalog";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductGallery } from "@/components/catalog/ProductGallery";
import { ProductDetailActions } from "@/components/catalog/ProductDetailActions";
import { RatingStars } from "@/components/catalog/RatingStars";
import { PriceDisplay } from "@/components/catalog/PriceDisplay";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductBadges } from "@/components/catalog/ProductBadge";
import { ProductOriginBadge } from "@/components/catalog/ProductOriginBadge";
import { DeliveryEstimator } from "@/components/catalog/DeliveryEstimator";
import { ShippingOptionsCard } from "@/components/catalog/ShippingOptionsCard";
import { TrustBadges } from "@/components/catalog/TrustBadges";
import { StockStatus } from "@/components/catalog/StockStatus";
import { ProductTabs } from "@/components/catalog/ProductTabs";
import { ProductDetailMobile } from "@/components/catalog/product-mobile/ProductDetailMobile";

interface ProductDetailPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ProductDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = await getProductBySlug(slug);
  if (!product) return { title: "Product Not Found — CHINA ORDER TZ" };

  return {
    title: `${product.name} — CHINA ORDER TZ`,
    description: product.description,
  };
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const { slug } = await params;
  const product = await getProductBySlug(slug);
  if (!product) notFound();

  const category = getCategoryBySlug(product.categorySlug);
  const relatedProducts = await getRelatedProducts(product);

  return (
    <div className="bg-white py-0 sm:py-10 lg:py-14">
      <div className="mx-auto max-w-7xl px-0 sm:px-6 lg:px-8">
        <div className="hidden px-4 sm:px-0 lg:block">
          <Breadcrumbs
            items={[
              { label: "Products", href: "/products" },
              ...(category
                ? [
                    { label: category.name, href: `/categories/${category.slug}` },
                    { label: product.name },
                  ]
                : [{ label: product.name }]),
            ]}
          />
        </div>

        {/* Desktop layout — unchanged */}
        <div className="mt-0 hidden lg:mt-8 lg:grid lg:grid-cols-2 lg:gap-16">
          <ProductGallery
            product={product}
            productName={product.name}
            fallbackEmoji={product.emoji}
            fallbackGradient={product.gradient}
          />

          <div className="lg:py-2">
            <div className="flex flex-wrap items-center gap-2">
              <ProductBadges badges={product.badges} />
              {category && (
                <span className="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
                  {category.icon} {category.name}
                </span>
              )}
            </div>

            {product.brand && (
              <p className="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
                {product.brand}
              </p>
            )}

            <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl lg:text-4xl">
              {product.name}
            </h1>

            <div className="mt-4">
              <RatingStars rating={product.rating} size="md" showValue reviewCount={product.reviews} />
            </div>

            <div className="mt-6">
              <PriceDisplay price={product.price} oldPrice={product.oldPrice} size="lg" />
            </div>

            <div className="mt-5 flex flex-wrap items-center gap-3">
              <StockStatus stock={product.stock} size="md" />
              <ProductOriginBadge origin={product.origin} size="md" />
            </div>

            {product.trustBadges.length > 0 && (
              <TrustBadges badges={product.trustBadges} size="md" className="mt-5" />
            )}

            {product.origin === "china" ? (
              <ShippingOptionsCard {...pickProductShippingContext(product)} className="mt-8" />
            ) : (
              <div className="mt-8 rounded-2xl border border-zinc-100 bg-zinc-50/50 p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
                  Delivery Information
                </p>
                <DeliveryEstimator origin={product.origin} variant="detail" className="mt-4" />
              </div>
            )}

            <ProductDetailActions product={product} />
          </div>
        </div>

        <div className="hidden lg:block">
          <ProductTabs
            description={product.description}
            features={product.features}
            specifications={product.specifications}
            reviews={product.customerReviews}
            reviewCount={product.reviews}
            averageRating={product.rating}
          />

          {relatedProducts.length > 0 && (
            <section className="mt-20 border-t border-zinc-100 pt-16">
              <div className="text-center sm:text-left">
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
                  You May Also Like
                </p>
                <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
                  Related Products
                </h2>
              </div>
              <div className="mt-10">
                <ProductGrid products={relatedProducts} />
              </div>
            </section>
          )}
        </div>

        {/* Mobile layout */}
        <ProductDetailMobile
          product={product}
          category={category}
          relatedProducts={relatedProducts}
        />
      </div>
    </div>
  );
}
