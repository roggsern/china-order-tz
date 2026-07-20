import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProductBySlug, getRelatedProducts, CatalogApiError } from "@/lib/catalog/products";
import { resolveCategoryBySlug } from "@/lib/catalog/categories";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductDetailDesktopExperience } from "@/components/catalog/ProductDetailDesktopExperience";
import { ProductRelatedSections } from "@/components/catalog/ProductRelatedSections";
import { ProductDetailMobile } from "@/components/catalog/product-mobile/ProductDetailMobile";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";

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

  try {
    const product = await getProductBySlug(slug);
    if (!product) notFound();

    const category = product.categorySlug
      ? await resolveCategoryBySlug(product.categorySlug)
      : undefined;
    const relatedProducts = await getRelatedProducts(product, 8);

    return (
      <div className="bg-white py-0 sm:py-10 lg:py-10">
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

          <ProductDetailDesktopExperience product={product} category={category} />

          <div className="hidden lg:block">
            <ProductRelatedSections product={product} relatedProducts={relatedProducts} />
          </div>

          <ProductDetailMobile
            product={product}
            category={category}
            relatedProducts={relatedProducts}
          />
        </div>
      </div>
    );
  } catch (error) {
    if (error instanceof CatalogApiError && error.statusCode === 404) {
      notFound();
    }

    const message =
      error instanceof CatalogApiError
        ? error.message
        : "Something went wrong while loading this product.";

    return (
      <div className="bg-white py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Breadcrumbs items={[{ label: "Products", href: "/products" }, { label: "Product" }]} />
          <div className="mt-8">
            <CatalogErrorState title="Unable to load product" message={message} />
          </div>
        </div>
      </div>
    );
  }
}
