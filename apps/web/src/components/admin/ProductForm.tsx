"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import type { Product, ProductFormData, ProductOrigin, ProductStatus } from "@/lib/types/catalog";
import { getAdminBrandsForOrigin } from "@/lib/admin/brand-options";
import { validateProductForm } from "@/lib/admin/product-utils";
import { getBrandBySlug } from "@/lib/catalog/brands";
import { categories, getSubcategories } from "@/lib/catalog/categories";
import { slugify } from "@/lib/catalog/utils";
import { productToFormData } from "@/components/admin/AdminProductsProvider";
import { ImageUploader } from "@/components/admin/ImageUploader";
import { ProductVariantSection } from "@/components/admin/ProductVariantSection";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { RichTextEditor } from "@/components/admin/RichTextEditor";
import { ToggleSwitch } from "@/components/admin/ToggleSwitch";
import { TrashIcon } from "@/components/home/icons";

interface ProductFormProps {
  initialData?: Product;
  isEditMode: boolean;
  onSubmit: (data: ProductFormData) => void;
  onDeleteProduct?: () => void;
}

const defaultFormData: ProductFormData = {
  name: "",
  slug: "",
  shortDescription: "",
  description: "",
  fullDescription: "",
  price: 0,
  oldPrice: 0,
  discountPercent: 0,
  rating: 4.5,
  reviews: 0,
  badge: "New",
  gradient: "from-zinc-500 to-zinc-700",
  emoji: "📦",
  origin: "china",
  brandSlug: "china-direct",
  brand: "China Direct",
  categorySlug: categories[0]?.slug ?? "",
  subcategorySlug: "",
  stock: 0,
  sku: "",
  weightKg: null,
  airCost: 18000,
  seaCost: 9500,
  airDeliveryDays: "7",
  seaDeliveryDays: "21",
  features: "",
  featured: false,
  bestSeller: false,
  trending: false,
  newArrival: false,
  status: "active",
  images: [],
  thumbnailImageId: null,
  variants: {},
};

const gradientOptions = [
  "from-zinc-900 to-zinc-700",
  "from-slate-800 to-blue-900",
  "from-rose-300 to-pink-500",
  "from-orange-400 to-red-500",
  "from-violet-500 to-purple-800",
  "from-teal-300 to-emerald-500",
  "from-amber-600 to-stone-800",
  "from-cyan-500 to-blue-700",
  "from-emerald-600 to-teal-800",
  "from-sky-400 to-blue-600",
];

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

export function ProductForm({ initialData, isEditMode, onSubmit, onDeleteProduct }: ProductFormProps) {
  const router = useRouter();
  const [form, setForm] = useState<ProductFormData>(
    initialData ? productToFormData(initialData) : defaultFormData,
  );
  const [isSaveLoading, setIsSaveLoading] = useState(false);
  const [autoSlug, setAutoSlug] = useState(!isEditMode);
  const [errors, setErrors] = useState<ReturnType<typeof validateProductForm>>({});

  const brandOptions = useMemo(
    () => getAdminBrandsForOrigin(form.origin),
    [form.origin],
  );

  const subcategoryOptions = useMemo(() => {
    if (form.origin === "tz" && form.brandSlug) {
      const brand = getBrandBySlug(form.brandSlug);
      return brand?.subcategories.map((item) => ({
        label: item.name,
        value: item.slug,
      })) ?? [];
    }

    return getSubcategories(form.categorySlug).map((item) => ({
      label: item,
      value: slugify(item),
    }));
  }, [form.origin, form.brandSlug, form.categorySlug]);

  const updateField = <K extends keyof ProductFormData>(key: K, value: ProductFormData[K]) => {
    setForm((prev) => {
      const next = { ...prev, [key]: value };

      if (key === "name" && autoSlug) {
        next.slug = slugify(String(value));
      }

      if (key === "origin") {
        const origin = value as ProductOrigin;
        const brands = getAdminBrandsForOrigin(origin);
        const firstBrand = brands[0];
        if (firstBrand) {
          next.brandSlug = firstBrand.slug;
          next.brand = firstBrand.name;
        }
        if (origin === "tz") {
          next.seaCost = 0;
          next.seaDeliveryDays = "";
        } else {
          next.seaCost = prev.seaCost || 9500;
          next.seaDeliveryDays = prev.seaDeliveryDays || "21";
        }
      }

      if (key === "brandSlug") {
        const brand = brandOptions.find((item) => item.slug === value);
        if (brand) next.brand = brand.name;
      }

      return next;
    });

    setErrors((prev) => ({ ...prev, [key]: undefined }));
  };

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    const validationErrors = validateProductForm(form);
    setErrors(validationErrors);

    if (Object.keys(validationErrors).length > 0) return;

    setIsSaveLoading(true);
    onSubmit(form);
    setTimeout(() => router.push("/admin/products"), 900);
  };

  const thumbnail = getProductPrimaryImage({
    name: form.name || "Product",
    emoji: form.emoji,
    gradient: form.gradient,
    images: form.images,
    image: form.images.find((image) => image.id === form.thumbnailImageId)?.url ?? form.images[0]?.url,
  });

  return (
    <form onSubmit={handleSubmit}>
      {isSaveLoading && (
        <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          Product {isEditMode ? "updated" : "created"} successfully.
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Basic information</h2>
            <div className="mt-4 space-y-4">
              <div>
                <label className="admin-label" htmlFor="name">
                  Product name *
                </label>
                <input
                  id="name"
                  type="text"
                  value={form.name}
                  onChange={(event) => updateField("name", event.target.value)}
                  className="admin-input mt-1.5"
                  placeholder="e.g. Pro Wireless Earbuds"
                />
                <FieldError message={errors.name} />
              </div>

              <div>
                <label className="admin-label" htmlFor="slug">
                  Slug (auto-generated)
                </label>
                <input
                  id="slug"
                  type="text"
                  value={form.slug}
                  onChange={(event) => {
                    setAutoSlug(false);
                    updateField("slug", event.target.value);
                  }}
                  className="admin-input mt-1.5 font-mono text-xs"
                  placeholder="product-handle"
                />
              </div>

              <div>
                <label className="admin-label" htmlFor="shortDescription">
                  Short description
                </label>
                <textarea
                  id="shortDescription"
                  rows={2}
                  value={form.shortDescription}
                  onChange={(event) => updateField("shortDescription", event.target.value)}
                  className="admin-input mt-1.5 resize-y"
                  placeholder="Brief summary for listings and cards…"
                />
              </div>

              <div>
                <label className="admin-label" htmlFor="fullDescription">
                  Full description (rich text)
                </label>
                <div className="mt-1.5">
                  <RichTextEditor
                    value={form.fullDescription}
                    onChange={(value) => updateField("fullDescription", value)}
                  />
                </div>
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Organization</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="origin">
                  Origin *
                </label>
                <select
                  id="origin"
                  value={form.origin}
                  onChange={(event) => updateField("origin", event.target.value as ProductOrigin)}
                  className="admin-input mt-1.5"
                >
                  <option value="china">Buy From China</option>
                  <option value="tz">Buy From TZ</option>
                </select>
                <FieldError message={errors.origin} />
              </div>

              <div>
                <label className="admin-label" htmlFor="brand">
                  Brand *
                </label>
                <select
                  id="brand"
                  value={form.brandSlug}
                  onChange={(event) => updateField("brandSlug", event.target.value)}
                  className="admin-input mt-1.5"
                >
                  {brandOptions.map((brand) => (
                    <option key={brand.slug} value={brand.slug}>
                      {brand.name}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.brandSlug} />
              </div>

              <div>
                <label className="admin-label" htmlFor="category">
                  Category *
                </label>
                <select
                  id="category"
                  value={form.categorySlug}
                  onChange={(event) => updateField("categorySlug", event.target.value)}
                  className="admin-input mt-1.5"
                >
                  {categories.map((category) => (
                    <option key={category.slug} value={category.slug}>
                      {category.name}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.categorySlug} />
              </div>

              <div>
                <label className="admin-label" htmlFor="subcategory">
                  Subcategory
                </label>
                <select
                  id="subcategory"
                  value={form.subcategorySlug}
                  onChange={(event) => updateField("subcategorySlug", event.target.value)}
                  className="admin-input mt-1.5"
                >
                  <option value="">Select subcategory</option>
                  {subcategoryOptions.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Media</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Upload multiple images, drag to reorder, and choose a thumbnail.
            </p>
            <div className="mt-4">
              <ImageUploader
                images={form.images}
                thumbnailImageId={form.thumbnailImageId}
                onChange={(images) => updateField("images", images)}
                onThumbnailChange={(id) => updateField("thumbnailImageId", id)}
                productName={form.name}
                gradient={form.gradient}
                emoji={form.emoji}
              />
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Pricing</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="price">
                  Price (TZS) *
                </label>
                <input
                  id="price"
                  type="number"
                  min={0}
                  value={form.price || ""}
                  onChange={(event) => updateField("price", Number(event.target.value))}
                  className="admin-input mt-1.5"
                />
                <FieldError message={errors.price} />
              </div>
              <div>
                <label className="admin-label" htmlFor="oldPrice">
                  Old price (TZS)
                </label>
                <input
                  id="oldPrice"
                  type="number"
                  min={0}
                  value={form.oldPrice || ""}
                  onChange={(event) => updateField("oldPrice", Number(event.target.value))}
                  className="admin-input mt-1.5"
                  placeholder="Optional"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="discountPercent">
                  Discount %
                </label>
                <input
                  id="discountPercent"
                  type="number"
                  min={0}
                  max={99}
                  value={form.discountPercent || ""}
                  onChange={(event) => updateField("discountPercent", Number(event.target.value))}
                  className="admin-input mt-1.5"
                  placeholder="0"
                />
              </div>
            </div>
          </section>

          <ProductVariantSection
            variants={form.variants}
            onChange={(variants) => updateField("variants", variants)}
          />

          {form.origin === "china" && (
            <section className="admin-card p-5">
              <h2 className="text-sm font-semibold text-zinc-900">China shipping</h2>
              <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="admin-label" htmlFor="airCost">
                    Air cost (TZS) *
                  </label>
                  <input
                    id="airCost"
                    type="number"
                    min={0}
                    value={form.airCost || ""}
                    onChange={(event) => updateField("airCost", Number(event.target.value))}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.airCost} />
                </div>
                <div>
                  <label className="admin-label" htmlFor="seaCost">
                    Sea cost (TZS) *
                  </label>
                  <input
                    id="seaCost"
                    type="number"
                    min={0}
                    value={form.seaCost || ""}
                    onChange={(event) => updateField("seaCost", Number(event.target.value))}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.seaCost} />
                </div>
                <div>
                  <label className="admin-label" htmlFor="airDeliveryDays">
                    Air delivery days *
                  </label>
                  <input
                    id="airDeliveryDays"
                    type="text"
                    placeholder='e.g. 5-7'
                    value={form.airDeliveryDays}
                    onChange={(event) => updateField("airDeliveryDays", event.target.value)}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.airDeliveryDays} />
                </div>
                <div>
                  <label className="admin-label" htmlFor="seaDeliveryDays">
                    Sea delivery days *
                  </label>
                  <input
                    id="seaDeliveryDays"
                    type="text"
                    placeholder='e.g. 35-45'
                    value={form.seaDeliveryDays}
                    onChange={(event) => updateField("seaDeliveryDays", event.target.value)}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.seaDeliveryDays} />
                </div>
              </div>
            </section>
          )}

          {form.origin === "tz" && (
            <section className="admin-card p-5">
              <h2 className="text-sm font-semibold text-zinc-900">Local delivery</h2>
              <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="admin-label" htmlFor="airCost">
                    Delivery cost (TZS) *
                  </label>
                  <input
                    id="airCost"
                    type="number"
                    min={0}
                    value={form.airCost || ""}
                    onChange={(event) => updateField("airCost", Number(event.target.value))}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.airCost} />
                </div>
                <div>
                  <label className="admin-label" htmlFor="airDeliveryDays">
                    Delivery days *
                  </label>
                  <input
                    id="airDeliveryDays"
                    type="text"
                    placeholder='e.g. 1-5'
                    value={form.airDeliveryDays}
                    onChange={(event) => updateField("airDeliveryDays", event.target.value)}
                    className="admin-input mt-1.5"
                  />
                  <FieldError message={errors.airDeliveryDays} />
                </div>
              </div>
            </section>
          )}

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Inventory</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <label className="admin-label" htmlFor="stock">
                  Stock quantity
                </label>
                <input
                  id="stock"
                  type="number"
                  min={0}
                  value={form.stock || ""}
                  onChange={(event) => updateField("stock", Number(event.target.value))}
                  className="admin-input mt-1.5"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="sku">
                  SKU
                </label>
                <input
                  id="sku"
                  type="text"
                  value={form.sku}
                  onChange={(event) => updateField("sku", event.target.value)}
                  className="admin-input mt-1.5"
                  placeholder="SKU-00001"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="weightKg">
                  Weight (kg, optional)
                </label>
                <input
                  id="weightKg"
                  type="number"
                  min={0}
                  step={0.01}
                  value={form.weightKg ?? ""}
                  onChange={(event) =>
                    updateField("weightKg", event.target.value ? Number(event.target.value) : null)
                  }
                  className="admin-input mt-1.5"
                />
              </div>
            </div>
          </section>
        </div>

        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Status</h2>
            <div className="mt-4">
              <label className="admin-label" htmlFor="status">
                Product status
              </label>
              <select
                id="status"
                value={form.status}
                onChange={(event) => updateField("status", event.target.value as ProductStatus)}
                className="admin-input mt-1.5"
              >
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="hidden">Hidden</option>
              </select>
            </div>
          </section>

          <section className="admin-card space-y-4 p-5">
            <ToggleSwitch
              id="featured"
              checked={form.featured}
              onChange={(checked) => updateField("featured", checked)}
              label="Featured product"
              description="Show in featured sections."
            />
            <ToggleSwitch
              id="bestSeller"
              checked={form.bestSeller}
              onChange={(checked) => updateField("bestSeller", checked)}
              label="Best seller"
              description="Highlight as a top-selling item."
            />
            <ToggleSwitch
              id="trending"
              checked={form.trending}
              onChange={(checked) => updateField("trending", checked)}
              label="Trending"
              description="Mark as currently trending."
            />
            <ToggleSwitch
              id="newArrival"
              checked={form.newArrival}
              onChange={(checked) => updateField("newArrival", checked)}
              label="New arrival"
              description="Show as a newly added product."
            />
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Preview</h2>
            <div className="mt-4 overflow-hidden rounded-lg border border-zinc-200">
              <ProductImageDisplay
                image={thumbnail}
                fallbackEmoji={form.emoji}
                fallbackGradient={form.gradient}
                className="aspect-square"
                emojiClassName="text-5xl"
              />
              <div className="p-3">
                <p className="truncate text-sm font-medium text-zinc-900">
                  {form.name || "Product title"}
                </p>
                <p className="mt-0.5 text-xs text-zinc-500">
                  {form.origin === "china" ? "Buy From China" : "Buy From TZ"}
                </p>
                <p className="mt-1 text-xs font-semibold text-zinc-900">
                  {form.price ? `TZS ${form.price.toLocaleString("en-TZ")}` : "—"}
                </p>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div className="sticky bottom-0 -mx-4 mt-6 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div className="flex items-center justify-between gap-3">
          {onDeleteProduct ? (
            <button
              type="button"
              onClick={onDeleteProduct}
              disabled={isSaveLoading}
              className="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
            >
              <TrashIcon className="h-4 w-4" />
              Delete product
            </button>
          ) : (
            <span />
          )}
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => router.push("/admin/products")}
              className="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
            >
              Discard
            </button>
            <button
              type="submit"
              disabled={isSaveLoading}
              className="rounded-lg bg-[#c9a227] px-5 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547] disabled:opacity-50"
            >
              {isSaveLoading ? "Saving…" : isEditMode ? "Save changes" : "Save product"}
            </button>
          </div>
        </div>
      </div>
    </form>
  );
}
