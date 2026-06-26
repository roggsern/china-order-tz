"use client";

import Link from "next/link";
import { use } from "react";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { StatusBadge } from "@/components/admin/StatusBadge";
import { formatAdminDate, getProductThumbnail } from "@/lib/admin/product-utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { formatPrice } from "@/lib/catalog/utils";
import { ChevronLeftIcon, EditIcon } from "@/components/home/icons";

interface ProductViewPageProps {
  params: Promise<{ id: string }>;
}

export default function ProductViewPage({ params }: ProductViewPageProps) {
  const { id } = use(params);
  const productId = Number(id);
  const { getProduct, isHydrated } = useAdminProducts();
  const product = getProduct(productId);

  if (!isHydrated) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center p-8 text-sm text-zinc-500">
        Loading product…
      </div>
    );
  }

  if (!product) {
    return (
      <div className="flex min-h-[50vh] flex-col items-center justify-center p-8 text-center">
        <span className="text-4xl">📦</span>
        <h1 className="mt-4 text-lg font-semibold text-zinc-900">Product not found</h1>
        <p className="mt-2 text-sm text-zinc-500">This product may have been deleted.</p>
        <Link
          href="/admin/products"
          className="mt-6 inline-flex items-center gap-1 text-sm font-medium text-[#8b6914] hover:underline"
        >
          <ChevronLeftIcon className="h-4 w-4" />
          Back to products
        </Link>
      </div>
    );
  }

  const category = getCategoryBySlug(product.categorySlug);
  const thumb = getProductThumbnail(product);
  const detailRows = [
    { label: "Brand", value: product.brand ?? "—" },
    { label: "Category", value: category?.name ?? product.categorySlug },
    { label: "Subcategory", value: product.subcategorySlug ?? "—" },
    {
      label: "Origin",
      value: product.origin === "china" ? "Buy From China" : "Buy From TZ",
    },
    { label: "SKU", value: product.sku ?? "—" },
    { label: "Stock", value: String(product.stock) },
    { label: "Weight", value: product.weightKg ? `${product.weightKg} kg` : "—" },
    { label: "Price", value: formatPrice(product.price) },
    { label: "Old price", value: product.oldPrice ? formatPrice(product.oldPrice) : "—" },
    {
      label: "Discount",
      value: product.discountPercent ? `${product.discountPercent}%` : "—",
    },
    {
      label: "Air cost",
      value: product.airCost ? formatPrice(product.airCost) : "—",
    },
    {
      label: "Sea cost",
      value: product.seaCost ? formatPrice(product.seaCost) : "—",
    },
    {
      label: "Air delivery",
      value: product.airDeliveryDays?.trim() ? product.airDeliveryDays : "—",
    },
    {
      label: "Sea delivery",
      value: product.seaDeliveryDays?.trim() ? product.seaDeliveryDays : "—",
    },
    { label: "Created", value: formatAdminDate(product.createdAt) },
  ];

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <Link
        href="/admin/products"
        className="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-[#8b6914]"
      >
        <ChevronLeftIcon className="h-4 w-4" />
        Products
      </Link>

      <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">{product.name}</h1>
            <StatusBadge status={product.status} />
          </div>
          <p className="mt-1 text-sm text-zinc-500">{product.slug}</p>
        </div>
        <Link
          href={`/admin/products/${product.id}/edit`}
          className="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
        >
          <EditIcon className="h-4 w-4" />
          Edit product
        </Link>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[320px_1fr]">
        <section className="admin-card overflow-hidden">
          <div
            className={`flex aspect-square items-center justify-center bg-gradient-to-br ${product.gradient}`}
          >
            {thumb?.url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={thumb.url} alt={product.name} className="h-full w-full object-cover" />
            ) : (
              <span className="text-6xl">{product.emoji}</span>
            )}
          </div>
          {product.images.length > 1 && (
            <div className="grid grid-cols-4 gap-2 border-t border-zinc-200 p-3">
              {product.images.map((image) => (
                <div
                  key={image.id}
                  className="aspect-square overflow-hidden rounded-md border border-zinc-200"
                >
                  {image.url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={image.url} alt={image.alt} className="h-full w-full object-cover" />
                  ) : (
                    <div
                      className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${image.gradient}`}
                    >
                      <span>{image.emoji}</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>

        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Overview</h2>
            <p className="mt-3 text-sm leading-relaxed text-zinc-600">
              {product.shortDescription ?? product.description}
            </p>
            <div className="mt-4 flex flex-wrap gap-2">
              {product.featured && (
                <span className="rounded-md bg-[#c9a227]/15 px-2 py-1 text-xs font-medium text-[#8b6914]">
                  Featured
                </span>
              )}
              {product.bestSeller && (
                <span className="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">
                  Best seller
                </span>
              )}
              {product.trending && (
                <span className="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">
                  Trending
                </span>
              )}
              {product.newArrival && (
                <span className="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">
                  New arrival
                </span>
              )}
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Details</h2>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              {detailRows.map((row) => (
                <div key={row.label} className="rounded-lg bg-zinc-50 px-3 py-2">
                  <dt className="text-[11px] font-medium uppercase tracking-wide text-zinc-400">
                    {row.label}
                  </dt>
                  <dd className="mt-0.5 text-sm font-medium text-zinc-800">{row.value}</dd>
                </div>
              ))}
            </dl>
          </section>

          {product.fullDescription && (
            <section className="admin-card p-5">
              <h2 className="text-sm font-semibold text-zinc-900">Full description</h2>
              <div
                className="prose prose-sm mt-4 max-w-none text-zinc-700"
                dangerouslySetInnerHTML={{ __html: product.fullDescription }}
              />
            </section>
          )}
        </div>
      </div>
    </div>
  );
}
