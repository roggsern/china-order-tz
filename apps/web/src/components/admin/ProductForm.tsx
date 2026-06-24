"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { Product, ProductFormData, ProductStatus } from "@/lib/types/catalog";
import { categories } from "@/lib/catalog/categories";
import { slugify } from "@/lib/catalog/utils";
import { productToFormData } from "@/components/admin/AdminProductsProvider";
import { ImageUploader } from "@/components/admin/ImageUploader";
import { ToggleSwitch } from "@/components/admin/ToggleSwitch";

interface ProductFormProps {
  initialData?: Product;
  mode: "create" | "edit";
  onSubmit: (data: ProductFormData) => void;
}

const defaultFormData: ProductFormData = {
  name: "",
  slug: "",
  description: "",
  price: 0,
  salePrice: 0,
  rating: 4.5,
  reviews: 0,
  badge: "New",
  gradient: "from-zinc-500 to-zinc-700",
  emoji: "📦",
  categorySlug: categories[0]?.slug ?? "",
  stock: 0,
  features: "",
  featured: false,
  status: "active",
  images: [],
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

export function ProductForm({ initialData, mode, onSubmit }: ProductFormProps) {
  const router = useRouter();
  const [form, setForm] = useState<ProductFormData>(
    initialData ? productToFormData(initialData) : defaultFormData,
  );
  const [submitted, setSubmitted] = useState(false);
  const [autoSlug, setAutoSlug] = useState(mode === "create");

  const updateField = <K extends keyof ProductFormData>(key: K, value: ProductFormData[K]) => {
    setForm((prev) => {
      const next = { ...prev, [key]: value };
      if (key === "name" && autoSlug) {
        next.slug = slugify(String(value));
      }
      return next;
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(form);
    setSubmitted(true);
    setTimeout(() => router.push("/admin/products"), 900);
  };

  const onSale = form.salePrice > 0 && form.salePrice < form.price;

  return (
    <form onSubmit={handleSubmit}>
      {submitted && (
        <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          Product {mode === "create" ? "created" : "updated"} successfully.
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        {/* Main column */}
        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Product details</h2>
            <div className="mt-4 space-y-4">
              <div>
                <label className="admin-label" htmlFor="name">
                  Title
                </label>
                <input
                  id="name"
                  type="text"
                  required
                  value={form.name}
                  onChange={(e) => updateField("name", e.target.value)}
                  className="admin-input mt-1.5"
                  placeholder="e.g. Pro Wireless Earbuds"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="description">
                  Description
                </label>
                <textarea
                  id="description"
                  required
                  rows={5}
                  value={form.description}
                  onChange={(e) => updateField("description", e.target.value)}
                  className="admin-input mt-1.5 resize-y"
                  placeholder="Describe your product..."
                />
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Media</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Upload multiple images. The first image is used as the main product photo.
            </p>
            <div className="mt-4">
              <ImageUploader
                images={form.images}
                onChange={(images) => updateField("images", images)}
                productName={form.name}
                gradient={form.gradient}
                emoji={form.emoji}
              />
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="emoji">
                  Fallback emoji
                </label>
                <input
                  id="emoji"
                  type="text"
                  value={form.emoji}
                  onChange={(e) => updateField("emoji", e.target.value)}
                  className="admin-input mt-1.5"
                  placeholder="📦"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="gradient">
                  Fallback gradient
                </label>
                <select
                  id="gradient"
                  value={form.gradient}
                  onChange={(e) => updateField("gradient", e.target.value)}
                  className="admin-input mt-1.5"
                >
                  {gradientOptions.map((g) => (
                    <option key={g} value={g}>
                      {g}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Pricing</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="price">
                  Price (TZS)
                </label>
                <div className="relative mt-1.5">
                  <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400">
                    TZS
                  </span>
                  <input
                    id="price"
                    type="number"
                    required
                    min={0}
                    value={form.price || ""}
                    onChange={(e) => updateField("price", Number(e.target.value))}
                    className="admin-input pl-12"
                  />
                </div>
              </div>
              <div>
                <label className="admin-label" htmlFor="salePrice">
                  Sale price (TZS)
                </label>
                <div className="relative mt-1.5">
                  <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400">
                    TZS
                  </span>
                  <input
                    id="salePrice"
                    type="number"
                    min={0}
                    value={form.salePrice || ""}
                    onChange={(e) => updateField("salePrice", Number(e.target.value))}
                    className="admin-input pl-12"
                    placeholder="Optional"
                  />
                </div>
                {onSale && (
                  <p className="mt-1 text-xs text-emerald-600">
                    On sale — customers pay {form.salePrice.toLocaleString()} TZS
                  </p>
                )}
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Inventory</h2>
            <div className="mt-4">
              <label className="admin-label" htmlFor="stock">
                Quantity in stock
              </label>
              <input
                id="stock"
                type="number"
                required
                min={0}
                value={form.stock || ""}
                onChange={(e) => updateField("stock", Number(e.target.value))}
                className="admin-input mt-1.5 max-w-xs"
              />
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Features</h2>
            <div className="mt-4">
              <label className="admin-label" htmlFor="features">
                Product features (one per line)
              </label>
              <textarea
                id="features"
                rows={4}
                value={form.features}
                onChange={(e) => updateField("features", e.target.value)}
                className="admin-input mt-1.5 resize-y"
                placeholder={"Active Noise Cancellation\n36hr Battery Life"}
              />
            </div>
          </section>
        </div>

        {/* Sidebar column */}
        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Status</h2>
            <div className="mt-4 space-y-3">
              <label className="admin-label" htmlFor="status">
                Product visibility
              </label>
              <select
                id="status"
                value={form.status}
                onChange={(e) => updateField("status", e.target.value as ProductStatus)}
                className="admin-input"
              >
                <option value="active">Active — visible on storefront</option>
                <option value="hidden">Hidden — not visible to customers</option>
              </select>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Organization</h2>
            <div className="mt-4 space-y-4">
              <div>
                <label className="admin-label" htmlFor="category">
                  Category
                </label>
                <select
                  id="category"
                  required
                  value={form.categorySlug}
                  onChange={(e) => updateField("categorySlug", e.target.value)}
                  className="admin-input mt-1.5"
                >
                  {categories.map((cat) => (
                    <option key={cat.slug} value={cat.slug}>
                      {cat.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="admin-label" htmlFor="slug">
                  URL handle
                </label>
                <input
                  id="slug"
                  type="text"
                  required
                  value={form.slug}
                  onChange={(e) => {
                    setAutoSlug(false);
                    updateField("slug", e.target.value);
                  }}
                  className="admin-input mt-1.5 font-mono text-xs"
                  placeholder="product-handle"
                />
              </div>
              <div>
                <label className="admin-label" htmlFor="badge">
                  Badge label
                </label>
                <input
                  id="badge"
                  type="text"
                  value={form.badge}
                  onChange={(e) => updateField("badge", e.target.value)}
                  className="admin-input mt-1.5"
                  placeholder="Hot Deal"
                />
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <ToggleSwitch
              id="featured"
              checked={form.featured}
              onChange={(checked) => updateField("featured", checked)}
              label="Featured product"
              description="Show this product in featured sections on the homepage."
            />
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Preview</h2>
            <div className="mt-4 overflow-hidden rounded-lg border border-zinc-200">
              <div
                className={`flex aspect-square items-center justify-center bg-gradient-to-br ${form.gradient}`}
              >
                {form.images[0]?.url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={form.images[0].url}
                    alt={form.name}
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <span className="text-5xl">{form.emoji || "📦"}</span>
                )}
              </div>
              <div className="p-3">
                <p className="truncate text-sm font-medium text-zinc-900">
                  {form.name || "Product title"}
                </p>
                <p className="mt-0.5 text-xs text-zinc-500">
                  {onSale ? (
                    <>
                      <span className="font-semibold text-red-600">
                        {form.salePrice.toLocaleString()} TZS
                      </span>
                      <span className="ml-1.5 line-through">{form.price.toLocaleString()} TZS</span>
                    </>
                  ) : (
                    <span className="font-semibold">{form.price.toLocaleString()} TZS</span>
                  )}
                </p>
              </div>
            </div>
          </section>
        </div>
      </div>

      {/* Sticky save bar — Shopify pattern */}
      <div className="sticky bottom-0 -mx-4 mt-6 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={() => router.push("/admin/products")}
            className="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Discard
          </button>
          <button
            type="submit"
            disabled={submitted}
            className="rounded-lg bg-[#c9a227] px-5 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547] disabled:opacity-50"
          >
            {mode === "create" ? "Save product" : "Save changes"}
          </button>
        </div>
      </div>
    </form>
  );
}
