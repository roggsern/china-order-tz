import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProductBySlug, getRelatedProducts } from "@/lib/catalog/products";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { getStockStatus } from "@/lib/catalog/utils";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductGallery } from "@/components/catalog/ProductGallery";
import { ProductDetailActions } from "@/components/catalog/ProductDetailActions";
import { RatingStars } from "@/components/catalog/RatingStars";
import { PriceDisplay } from "@/components/catalog/PriceDisplay";
import { ProductGrid } from "@/components/catalog/ProductGrid";

interface ProductDetailPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ProductDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = getProductBySlug(slug);
  if (!product) return { title: "Product Not Found — CHINA ORDER TZ" };

  return {
    title: `${product.name} — CHINA ORDER TZ`,
    description: product.description,
  };
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const { slug } = await params;
  const product = getProductBySlug(slug);
  if (!product) notFound();

  const category = getCategoryBySlug(product.categorySlug);
  const stockStatus = getStockStatus(product.stock);
  const relatedProducts = getRelatedProducts(product);

  const stockClasses = {
    "in-stock": "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    "low-stock": "bg-amber-50 text-amber-700 ring-amber-600/20",
    "out-of-stock": "bg-red-50 text-red-700 ring-red-600/20",
  };

  return (
    <div className="bg-white py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            { label: "Products", href: "/products" },
            ...(category
              ? [
                  { label: category.name, href: `/products?category=${category.slug}` },
                  { label: product.name },
                ]
              : [{ label: product.name }]),
          ]}
        />

        <div className="mt-8 grid gap-10 lg:grid-cols-2 lg:gap-16">
          <ProductGallery images={product.images} productName={product.name} />

          <div>
            <div className="flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                {product.badge}
              </span>
              {category && (
                <span className="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
                  {category.icon} {category.name}
                </span>
              )}
            </div>

            <h1 className="mt-4 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
              {product.name}
            </h1>

            <div className="mt-4">
              <RatingStars rating={product.rating} size="md" showValue reviewCount={product.reviews} />
            </div>

            <div className="mt-6">
              <PriceDisplay price={product.price} oldPrice={product.oldPrice} size="lg" />
            </div>

            <div className="mt-4">
              <span
                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${stockClasses[stockStatus.variant]}`}
              >
                <span
                  className={`h-1.5 w-1.5 rounded-full ${
                    stockStatus.variant === "in-stock"
                      ? "bg-emerald-500"
                      : stockStatus.variant === "low-stock"
                        ? "bg-amber-500"
                        : "bg-red-500"
                  }`}
                />
                {stockStatus.label}
              </span>
            </div>

            <p className="mt-6 text-base leading-relaxed text-zinc-600">{product.description}</p>

            {product.features.length > 0 && (
              <ul className="mt-6 grid gap-2 sm:grid-cols-2">
                {product.features.map((feature) => (
                  <li key={feature} className="flex items-center gap-2 text-sm text-zinc-600">
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#c9a227]/10 text-[10px] text-[#8b6914]">
                      ✓
                    </span>
                    {feature}
                  </li>
                ))}
              </ul>
            )}

            <ProductDetailActions product={product} />
          </div>
        </div>

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
    </div>
  );
}
