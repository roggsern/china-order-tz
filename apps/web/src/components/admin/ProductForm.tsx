"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import type { Product, ProductFormData, ProductStatus, ProductType } from "@/lib/types/catalog";
import {
  fetchAdminBrands,
  fetchAdminCategories,
  type AdminBrand,
  type AdminCategory,
  AdminCatalogApiError,
} from "@/lib/api/admin-catalog";
import { validateProductForm } from "@/lib/admin/product-utils";
import { productTypeToOrigin } from "@/lib/catalog/product-type";
import { slugify } from "@/lib/catalog/utils";
import { productToFormData } from "@/components/admin/AdminProductsProvider";
import { ImageUploader } from "@/components/admin/ImageUploader";
import { ProductConfigurationGrid } from "@/components/admin/ProductConfigurationGrid";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { WholesalePricingEditor } from "@/components/admin/WholesalePricingEditor";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { RichTextEditor } from "@/components/admin/RichTextEditor";
import { ToggleSwitch } from "@/components/admin/ToggleSwitch";
import { TrashIcon } from "@/components/home/icons";

interface ProductFormProps {
  initialData?: Product;
  isEditMode: boolean;
  onSubmit: (
    data: ProductFormData,
    options?: { pendingFiles?: Map<number, File> },
  ) => void | Promise<void>;
  onDeleteProduct?: () => void | Promise<void>;
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
  rating: 0,
  reviews: 0,
  badge: "",
  gradient: "from-zinc-500 to-zinc-700",
  emoji: "📦",
  type: "china",
  origin: "china",
  categoryId: "",
  parentCategoryId: "",
  brandId: "",
  brandSlug: "",
  brand: "",
  categorySlug: "",
  subcategorySlug: "",
  stock: 0,
  sku: "",
  skuOverride: false,
  weightKg: null,
  airCost: 18000,
  seaCost: 9500,
  airAvailable: true,
  seaAvailable: true,
  airNotes: "",
  seaNotes: "",
  airDeliveryDays: "",
  seaDeliveryDays: "",
  features: "",
  featured: false,
  bestSeller: false,
  trending: false,
  newArrival: false,
  status: "active",
  isDemo: false,
  wholesaleEnabled: false,
  priceTiers: [],
  images: [],
  thumbnailImageId: null,
  variants: {},
  configurations: [],
};

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
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [rootCategories, setRootCategories] = useState<AdminCategory[]>([]);
  const [subcategories, setSubcategories] = useState<AdminCategory[]>([]);
  const [brands, setBrands] = useState<AdminBrand[]>([]);
  const [optionsError, setOptionsError] = useState<string | null>(null);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const pendingFilesRef = useRef<Map<number, File>>(new Map());

  const catalogOrigin = form.origin === "tz" ? "tz" : "china";

  useEffect(() => {
    let cancelled = false;

    async function loadRootCategories() {
      setOptionsLoading(true);
      setOptionsError(null);

      try {
        const nextRoots = await fetchAdminCategories({
          origin: catalogOrigin,
          rootsOnly: true,
        });

        if (cancelled) return;

        setRootCategories(nextRoots);

        setForm((prev) => {
          const parentStillValid = nextRoots.some((item) => item.id === prev.parentCategoryId);
          if (parentStillValid) {
            return prev;
          }

          // Edit mode: leaf may be a root or a child — resolve parent from payload.
          if (prev.parentCategoryId && nextRoots.some((item) => item.id === prev.parentCategoryId)) {
            return prev;
          }

          if (prev.categoryId && nextRoots.some((item) => item.id === prev.categoryId)) {
            return {
              ...prev,
              parentCategoryId: prev.categoryId,
              categorySlug: nextRoots.find((item) => item.id === prev.categoryId)?.slug ?? prev.categorySlug,
            };
          }

          return {
            ...prev,
            parentCategoryId: "",
            categoryId: "",
            categorySlug: "",
            subcategorySlug: "",
            brandId: "",
            brandSlug: "",
            brand: "",
            configurations: [],
          };
        });
      } catch (err) {
        if (!cancelled) {
          setOptionsError(
            err instanceof AdminCatalogApiError
              ? err.message
              : "Unable to load categories.",
          );
        }
      } finally {
        if (!cancelled) {
          setOptionsLoading(false);
        }
      }
    }

    void loadRootCategories();

    return () => {
      cancelled = true;
    };
  }, [catalogOrigin]);

  useEffect(() => {
    let cancelled = false;
    const parentId = form.parentCategoryId;

    if (!parentId) {
      setSubcategories([]);
      return;
    }

    async function loadSubcategories() {
      try {
        const children = await fetchAdminCategories({ parentId });
        if (cancelled) return;
        setSubcategories(children);

        setForm((prev) => {
          if (!prev.categoryId) {
            return prev;
          }

          const leafIsParent = prev.categoryId === parentId;
          const leafIsChild = children.some((item) => item.id === prev.categoryId);

          if (leafIsParent || leafIsChild) {
            return prev;
          }

          // Incoming edit: categoryId is leaf with parentCategoryId from API.
          if (prev.parentCategoryId === parentId) {
            return prev;
          }

          return {
            ...prev,
            categoryId: "",
            subcategorySlug: "",
            brandId: "",
            brandSlug: "",
            brand: "",
            configurations: [],
          };
        });
      } catch {
        if (!cancelled) {
          setSubcategories([]);
        }
      }
    }

    void loadSubcategories();

    return () => {
      cancelled = true;
    };
  }, [form.parentCategoryId]);

  useEffect(() => {
    let cancelled = false;
    const leafId = form.categoryId || form.parentCategoryId;

    async function loadBrands() {
      try {
        const nextBrands = await fetchAdminBrands(
          leafId ? { categoryId: leafId } : {},
        );
        if (cancelled) return;
        setBrands(nextBrands);

        setForm((prev) => {
          if (!prev.brandId) return prev;
          if (nextBrands.some((item) => item.id === prev.brandId)) return prev;
          return {
            ...prev,
            brandId: "",
            brandSlug: "",
            brand: "",
          };
        });
      } catch {
        if (!cancelled) {
          setBrands([]);
        }
      }
    }

    void loadBrands();

    return () => {
      cancelled = true;
    };
  }, [form.categoryId, form.parentCategoryId]);

  // Resolve parentCategoryId when editing a leaf subcategory.
  useEffect(() => {
    if (!isEditMode || form.parentCategoryId || !form.categoryId) {
      return;
    }

    let cancelled = false;

    async function resolveParent() {
      try {
        const all = await fetchAdminCategories({ origin: catalogOrigin });
        if (cancelled) return;
        const leaf = all.find((item) => item.id === form.categoryId);
        if (!leaf) return;

        if (!leaf.parentId) {
          setForm((prev) => ({
            ...prev,
            parentCategoryId: leaf.id,
            categorySlug: leaf.slug,
          }));
          return;
        }

        setForm((prev) => ({
          ...prev,
          parentCategoryId: leaf.parentId ?? "",
          subcategorySlug: leaf.slug,
          categorySlug: all.find((item) => item.id === leaf.parentId)?.slug ?? prev.categorySlug,
        }));
      } catch {
        // Parent resolution is best-effort for edit mode.
      }
    }

    void resolveParent();

    return () => {
      cancelled = true;
    };
  }, [isEditMode, form.categoryId, form.parentCategoryId, catalogOrigin]);

  const updateField = <K extends keyof ProductFormData>(key: K, value: ProductFormData[K]) => {
    setForm((prev) => {
      const next = { ...prev, [key]: value };

      if (key === "name" && autoSlug) {
        next.slug = slugify(String(value));
      }

      if (key === "type") {
        const type = value as ProductType;
        next.type = type;
        next.origin = productTypeToOrigin(type);
        next.parentCategoryId = "";
        next.categoryId = "";
        next.categorySlug = "";
        next.subcategorySlug = "";
        next.brandId = "";
        next.brandSlug = "";
        next.brand = "";
        next.configurations = [];
        if (type === "local") {
          next.seaCost = 0;
          next.seaDeliveryDays = "";
          next.airAvailable = false;
          next.seaAvailable = false;
          next.airNotes = "";
          next.seaNotes = "";
        } else if (!next.airCost) {
          next.airCost = 18000;
          next.seaCost = 9500;
          next.airAvailable = true;
          next.seaAvailable = true;
        }
      }

      if (key === "parentCategoryId") {
        const category = rootCategories.find((item) => item.id === value);
        next.categorySlug = category?.slug ?? "";
        next.categoryId = "";
        next.subcategorySlug = "";
        next.brandId = "";
        next.brandSlug = "";
        next.brand = "";
        next.configurations = [];
      }

      if (key === "categoryId") {
        const subcategory = subcategories.find((item) => item.id === value);
        if (value && subcategory) {
          next.subcategorySlug = subcategory.slug;
          next.categoryId = subcategory.id;
        } else if (!value && next.parentCategoryId) {
          // No subcategory chosen — persist parent as leaf when branch is empty.
          next.categoryId = next.parentCategoryId;
          next.subcategorySlug = "";
        }
        next.brandId = "";
        next.brandSlug = "";
        next.brand = "";
        next.configurations = [];
      }

      if (key === "brandId") {
        const brand = brands.find((item) => item.id === value);
        next.brandSlug = brand?.slug ?? "";
        next.brand = brand?.name ?? "";
      }

      if (key === "skuOverride" && value === false) {
        next.sku = "";
      }

      return next;
    });

    setErrors((prev) => ({ ...prev, [key]: undefined }));
    setSubmitError(null);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();

    const payload: ProductFormData = {
      ...form,
      // Persist leaf: subcategory if chosen, otherwise parent category.
      categoryId:
        form.categoryId ||
        form.parentCategoryId ||
        "",
      sku: form.skuOverride ? form.sku : "",
    };

    const validationErrors = validateProductForm(payload);
    setErrors(validationErrors);
    setSubmitError(null);

    if (Object.keys(validationErrors).length > 0) return;

    setIsSaveLoading(true);

    try {
      await onSubmit(payload, { pendingFiles: pendingFilesRef.current });
      pendingFilesRef.current.clear();
      router.push("/admin/products");
    } catch (err) {
      setSubmitError(
        err instanceof AdminCatalogApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : "Unable to save product.",
      );
    } finally {
      setIsSaveLoading(false);
    }
  };

  const thumbnail = getProductPrimaryImage({
    name: form.name || "Product",
    emoji: form.emoji,
    gradient: form.gradient,
    images: form.images,
    image: form.images.find((image) => image.id === form.thumbnailImageId)?.url ?? form.images[0]?.url,
  });

  const catalogSelectionValid =
    Boolean(form.parentCategoryId) &&
    rootCategories.length > 0 &&
    (subcategories.length === 0 ||
      (Boolean(form.categoryId) && form.categoryId !== form.parentCategoryId)) &&
    !optionsLoading &&
    !optionsError;

  return (
    <form onSubmit={(event) => void handleSubmit(event)}>
      {submitError ? (
        <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {submitError}
        </div>
      ) : null}

      {optionsError ? (
        <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          {optionsError}
        </div>
      ) : null}

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
                  Slug (optional — auto from name)
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
                  Full description
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
            <h2 className="text-sm font-semibold text-zinc-900">Catalog classification</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Commerce channel → Category → Subcategory → Brand. Taxonomy is database-driven.
            </p>

            <div className="mt-4">
              <span className="admin-label">Commerce Channel *</span>
              <div className="mt-2 flex flex-wrap gap-2" role="tablist" aria-label="Commerce channel">
                {(
                  [
                    { id: "china" as const, label: "Order From China" },
                    { id: "local" as const, label: "Buy From TZ" },
                  ] as const
                ).map((option) => {
                  const isActive = form.type === option.id;
                  return (
                    <button
                      key={option.id}
                      type="button"
                      role="tab"
                      aria-selected={isActive}
                      onClick={() => updateField("type", option.id)}
                      className={`rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200 ${
                        isActive
                          ? "bg-[#c9a227] text-zinc-900 shadow-sm ring-1 ring-[#c9a227]/50"
                          : "bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200/80 hover:bg-zinc-100 hover:text-zinc-900"
                      }`}
                    >
                      {option.label}
                    </button>
                  );
                })}
              </div>
              <FieldError message={errors.type} />
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="parentCategoryId">
                  Category *
                </label>
                <select
                  id="parentCategoryId"
                  value={form.parentCategoryId}
                  onChange={(event) => updateField("parentCategoryId", event.target.value)}
                  className="admin-input mt-1.5"
                  disabled={optionsLoading || rootCategories.length === 0}
                >
                  <option value="">
                    {optionsLoading
                      ? "Loading…"
                      : rootCategories.length === 0
                        ? "No categories configured."
                        : "Select category"}
                  </option>
                  {rootCategories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.categorySlug} />
              </div>

              <div>
                <label className="admin-label" htmlFor="categoryId">
                  Subcategory
                </label>
                <select
                  id="categoryId"
                  value={
                    form.categoryId && form.categoryId !== form.parentCategoryId
                      ? form.categoryId
                      : ""
                  }
                  onChange={(event) => updateField("categoryId", event.target.value)}
                  className="admin-input mt-1.5"
                  disabled={!form.parentCategoryId || optionsLoading}
                >
                  <option value="">
                    {!form.parentCategoryId
                      ? "Select a category first"
                      : subcategories.length === 0
                        ? "No subcategories configured."
                        : "Select subcategory"}
                  </option>
                  {subcategories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="sm:col-span-2">
                <label className="admin-label" htmlFor="brandId">
                  Brand
                </label>
                <select
                  id="brandId"
                  value={form.brandId}
                  onChange={(event) => updateField("brandId", event.target.value)}
                  className="admin-input mt-1.5"
                  disabled={optionsLoading || brands.length === 0}
                >
                  <option value="">
                    {brands.length === 0 ? "No brands configured." : "No brand"}
                  </option>
                  {brands.map((brand) => (
                    <option key={brand.id} value={brand.id}>
                      {brand.name}
                    </option>
                  ))}
                </select>
                <p className="mt-1 text-xs text-zinc-500">
                  When brand↔category links exist, this list is filtered to linked brands.
                </p>
                <FieldError message={errors.brandSlug} />
              </div>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Media</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Images are stored on the Laravel public disk and shown on the storefront.
            </p>
            <div className="mt-4">
              <ImageUploader
                images={form.images}
                thumbnailImageId={form.thumbnailImageId}
                onChange={(images) => updateField("images", images)}
                onThumbnailChange={(id) => updateField("thumbnailImageId", id)}
                catalogProductId={initialData?.catalogProductId ?? null}
                pendingFilesRef={pendingFilesRef}
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
                  Base price (TZS) *
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
                  Compare-at price (TZS)
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
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Base SKU &amp; shipping identity</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="admin-label" htmlFor="sku">
                  Base SKU
                </label>
                <label className="mt-2 flex items-center gap-2 text-xs text-zinc-600">
                  <input
                    type="checkbox"
                    checked={form.skuOverride}
                    onChange={(event) => updateField("skuOverride", event.target.checked)}
                  />
                  Override auto-generated SKU
                </label>
                <input
                  id="sku"
                  type="text"
                  value={form.sku}
                  onChange={(event) => updateField("sku", event.target.value)}
                  className="admin-input mt-1.5"
                  placeholder={form.skuOverride ? "Enter custom SKU" : "Auto-generated on save"}
                  disabled={!form.skuOverride}
                  required={form.skuOverride}
                />
                <p className="mt-1 text-xs text-zinc-500">
                  {form.skuOverride
                    ? "Custom SKU will be saved as entered."
                    : "A unique SKU is generated from origin and category on save. Configuration SKUs still use this base + Product Type pattern."}
                </p>
                <FieldError message={errors.sku} />
              </div>
              <div>
                <label className="admin-label" htmlFor="weightKg">
                  Weight (kg)
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

          <ProductConfigurationGrid
            categoryId={form.categoryId || form.parentCategoryId}
            baseSku={form.sku}
            basePrice={form.price}
            configurations={form.configurations}
            onChange={(configurations) => updateField("configurations", configurations)}
            simpleStock={form.stock}
            onSimpleStockChange={(stock) => updateField("stock", stock)}
          />
          <FieldError message={errors.configurations} />

          {form.type === "china" && (
            <section className="admin-card p-5">
              <h2 className="text-sm font-semibold text-zinc-900">Manage Shipping Options</h2>
              <p className="mt-1 text-xs text-zinc-500">
                Enter prices manually. The system never calculates shipping costs. Leave a mode
                unavailable to hide it from customers. Customer Agent ignores these prices.
              </p>

              <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-zinc-200 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-semibold text-zinc-900">Air Freight</p>
                    <label className="flex items-center gap-2 text-xs text-zinc-600">
                      <input
                        type="checkbox"
                        checked={form.airAvailable}
                        onChange={(event) =>
                          updateField("airAvailable", event.target.checked)
                        }
                      />
                      Available
                    </label>
                  </div>
                  <div className="mt-3">
                    <label className="admin-label" htmlFor="airCost">
                      Price (TZS)
                    </label>
                    <input
                      id="airCost"
                      type="number"
                      min={0}
                      disabled={!form.airAvailable}
                      value={form.airAvailable ? form.airCost || "" : ""}
                      onChange={(event) =>
                        updateField("airCost", Number(event.target.value))
                      }
                      className="admin-input mt-1.5"
                    />
                    <FieldError message={errors.airCost} />
                  </div>
                  <div className="mt-3">
                    <label className="admin-label" htmlFor="airNotes">
                      Notes
                    </label>
                    <input
                      id="airNotes"
                      type="text"
                      disabled={!form.airAvailable}
                      value={form.airNotes}
                      onChange={(event) => updateField("airNotes", event.target.value)}
                      className="admin-input mt-1.5"
                      placeholder="Optional"
                    />
                  </div>
                </div>

                <div className="rounded-xl border border-zinc-200 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-semibold text-zinc-900">Sea Freight</p>
                    <label className="flex items-center gap-2 text-xs text-zinc-600">
                      <input
                        type="checkbox"
                        checked={form.seaAvailable}
                        onChange={(event) =>
                          updateField("seaAvailable", event.target.checked)
                        }
                      />
                      Available
                    </label>
                  </div>
                  <div className="mt-3">
                    <label className="admin-label" htmlFor="seaCost">
                      Price (TZS)
                    </label>
                    <input
                      id="seaCost"
                      type="number"
                      min={0}
                      disabled={!form.seaAvailable}
                      value={form.seaAvailable ? form.seaCost || "" : ""}
                      onChange={(event) =>
                        updateField("seaCost", Number(event.target.value))
                      }
                      className="admin-input mt-1.5"
                    />
                    <FieldError message={errors.seaCost} />
                  </div>
                  <div className="mt-3">
                    <label className="admin-label" htmlFor="seaNotes">
                      Notes
                    </label>
                    <input
                      id="seaNotes"
                      type="text"
                      disabled={!form.seaAvailable}
                      value={form.seaNotes}
                      onChange={(event) => updateField("seaNotes", event.target.value)}
                      className="admin-input mt-1.5"
                      placeholder="Optional"
                    />
                  </div>
                </div>
              </div>
            </section>
          )}

          {form.configurations.length === 0 ? (
            <section className="admin-card p-5">
              <h2 className="text-sm font-semibold text-zinc-900">Inventory</h2>
              <p className="mt-1 text-xs text-zinc-500">
                Used when the Product Type has no configurations, or before you generate any.
              </p>
              <div className="mt-4 max-w-xs">
                <label className="admin-label" htmlFor="stock">
                  Stock quantity *
                </label>
                <input
                  id="stock"
                  type="number"
                  min={0}
                  value={form.stock}
                  onChange={(event) => updateField("stock", Number(event.target.value))}
                  className="admin-input mt-1.5"
                />
                <FieldError message={errors.stock} />
              </div>
            </section>
          ) : null}

          {form.configurations.length === 0 ? (
            <WholesalePricingEditor
              enabled={form.wholesaleEnabled}
              onEnabledChange={(enabled) => updateField("wholesaleEnabled", enabled)}
              tiers={form.priceTiers}
              onChange={(tiers) => updateField("priceTiers", tiers)}
              basePrice={form.price}
            />
          ) : null}
        </div>

        <div className="space-y-6">
          <section className="admin-card p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Product Status</h2>
            <div className="mt-4">
              <label className="admin-label" htmlFor="status">
                Lifecycle
              </label>
              <select
                id="status"
                value={
                  form.status === "hidden"
                    ? "archived"
                    : form.status === "draft" ||
                        form.status === "out_of_stock" ||
                        form.status === "archived"
                      ? form.status
                      : "active"
                }
                onChange={(event) => updateField("status", event.target.value as ProductStatus)}
                className="admin-input mt-1.5"
              >
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="out_of_stock">Out of Stock</option>
                <option value="archived">Archived</option>
              </select>
              <p className="mt-1 text-[11px] text-zinc-500">
                Only Active products are purchasable on the storefront. Out of Stock remains listed.
              </p>
            </div>
          </section>

          <section className="admin-card space-y-4 p-5">
            <ToggleSwitch
              id="featured"
              checked={form.featured}
              onChange={(checked) => updateField("featured", checked)}
              label="Featured product"
              description="Persists as is_featured on the product."
            />
            <ToggleSwitch
              id="isDemo"
              checked={form.isDemo}
              onChange={(checked) => updateField("isDemo", checked)}
              label="Demo / test product"
              description="Excluded from storefront catalog and admin revenue analytics."
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
                  {form.type === "china" ? "China Order" : "Buy from Dar"}
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
              onClick={() => {
                void onDeleteProduct();
              }}
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
              disabled={
                isSaveLoading ||
                optionsLoading ||
                Boolean(optionsError) ||
                !catalogSelectionValid
              }
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
