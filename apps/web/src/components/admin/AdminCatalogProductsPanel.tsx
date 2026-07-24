"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { parseCatalogProductEditTab } from "@/lib/admin/product-id-map";
import {
  calculateProductPublishReadiness,
  formatPublishReadinessMissingLabels,
  isLeafCategoryId,
} from "@/lib/admin/product-publish-readiness";
import { isChinaImportCommerceChannel, hasPublishableShippingOption } from "@/lib/admin/product-shipping-sync";
import {
  mergeProductStoreIdIntoPayload,
  resolveProductStoreIdForReadiness,
  shouldShowProductStoreSelector,
  validateProductStoreAssignment,
} from "@/lib/admin/product-store-assignment";
import { PublishReadinessChecklist } from "@/components/admin/PublishReadinessChecklist";
import { ProductMediaManager } from "@/components/admin/ProductMediaManager";
import { ProductShippingManager } from "@/components/admin/ProductShippingManager";
import { ProductStockManager } from "@/components/admin/ProductStockManager";
import { ProductSpecificationsManager } from "@/components/admin/ProductSpecificationsManager";
import { ProductVariantsManager } from "@/components/admin/ProductVariantsManager";
import {
  AdminCatalogApiError,
  commerceJourneyToChannelCode,
  createAdminCatalogProduct,
  deleteAdminCatalogProduct,
  fetchAdminBrands,
  fetchAdminCatalogProduct,
  fetchAdminCatalogProductTypes,
  fetchAdminCatalogProductsPage,
  fetchAdminCategories,
  fetchAdminCommerceChannels,
  fetchAdminDepartments,
  fetchAdminProductVariants,
  fetchAdminProductShippingOptions,
  fetchAdminStores,
  resolveAdminCommerceChannelId,
  restoreAdminCatalogProduct,
  updateAdminCatalogProduct,
  type AdminApiCommerceChannel,
  type AdminBrand,
  type AdminCatalogProduct,
  type AdminCatalogProductType,
  type AdminCategory,
  type AdminDepartment,
  type AdminProductVariant,
  type AdminProductShippingOption,
  type AdminStoreOption,
  type CommerceJourney,
} from "@/lib/api/admin-catalog";

type ProductFormState = {
  id?: string;
  name: string;
  sku: string;
  price: number;
  shortDescription: string;
  description: string;
  commerceJourney: CommerceJourney | "";
  storeId: string;
  departmentId: string;
  categoryId: string;
  subcategoryId: string;
  catalogProductTypeId: string;
  brandId: string;
  status: "draft" | "active" | "archived";
  visibility: "public" | "private" | "hidden";
  sortOrder: number;
  isFeatured: boolean;
};

const emptyForm = (): ProductFormState => ({
  name: "",
  sku: "",
  price: 0,
  shortDescription: "",
  description: "",
  commerceJourney: "",
  storeId: "",
  departmentId: "",
  categoryId: "",
  subcategoryId: "",
  catalogProductTypeId: "",
  brandId: "",
  status: "draft",
  visibility: "public",
  sortOrder: 0,
  isFeatured: false,
});

/** Category id plus every parent up the tree (leaf → root). */
function categoryAncestorIds(
  categoryId: string,
  categories: AdminCategory[],
): Set<string> {
  const ids = new Set<string>();
  let current = categories.find((item) => item.id === categoryId);

  while (current) {
    ids.add(current.id);
    current = current.parentId
      ? categories.find((item) => item.id === current!.parentId)
      : undefined;
  }

  return ids;
}

function productTypeMatchesCategoryScope(
  typeSubcategoryId: string,
  scopeCategoryId: string,
  categories: AdminCategory[],
): boolean {
  return categoryAncestorIds(scopeCategoryId, categories).has(typeSubcategoryId);
}

const PAGE_SIZE = 15;

export function AdminCatalogProductsPanel() {
  const [products, setProducts] = useState<AdminCatalogProduct[]>([]);
  const [trashed, setTrashed] = useState<AdminCatalogProduct[]>([]);
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [productTypes, setProductTypes] = useState<AdminCatalogProductType[]>([]);
  const [brands, setBrands] = useState<AdminBrand[]>([]);
  const [commerceChannels, setCommerceChannels] = useState<AdminApiCommerceChannel[]>([]);
  const [stores, setStores] = useState<AdminStoreOption[]>([]);
  const [storesLoading, setStoresLoading] = useState(false);
  const [storesError, setStoresError] = useState<string | null>(null);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [filterDepartmentId, setFilterDepartmentId] = useState("");
  const [filterCategoryId, setFilterCategoryId] = useState("");
  const [filterSubcategoryId, setFilterSubcategoryId] = useState("");
  const [filterTypeId, setFilterTypeId] = useState("");
  const [filterBrandId, setFilterBrandId] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | "draft" | "active" | "archived">("all");
  const [featuredFilter, setFeaturedFilter] = useState<"all" | "featured" | "standard">("all");
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<ProductFormState | null>(null);
  const [formTab, setFormTab] = useState<
    "details" | "media" | "specifications" | "variants" | "shipping" | "stock"
  >("details");
  const [saving, setSaving] = useState(false);
  const [publishVariants, setPublishVariants] = useState<AdminProductVariant[]>([]);
  const [publishContext, setPublishContext] = useState<AdminCatalogProduct | null>(null);
  const [publishShippingOptions, setPublishShippingOptions] = useState<
    AdminProductShippingOption[] | null
  >(null);
  const deepLinkEditHandledRef = useRef<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [
    debouncedSearch,
    filterDepartmentId,
    filterCategoryId,
    filterSubcategoryId,
    filterTypeId,
    filterBrandId,
    statusFilter,
    featuredFilter,
  ]);

  const reloadLookups = useCallback(async () => {
    setStoresLoading(true);
    setStoresError(null);
    const [nextDepartments, nextCategories, nextTypes, nextBrands, nextChannels, nextStores] =
      await Promise.all([
        fetchAdminDepartments(),
        fetchAdminCategories(),
        fetchAdminCatalogProductTypes(),
        fetchAdminBrands(),
        fetchAdminCommerceChannels().catch(() => [] as AdminApiCommerceChannel[]),
        fetchAdminStores().catch((err: unknown) => {
          setStoresError(
            err instanceof AdminCatalogApiError
              ? err.message
              : "Unable to load stores from the API.",
          );
          return [] as AdminStoreOption[];
        }),
      ]);
    setDepartments(nextDepartments);
    setCategories(nextCategories);
    setProductTypes(nextTypes);
    setBrands(nextBrands);
    setCommerceChannels(nextChannels);
    setStores(nextStores);
    setStoresLoading(false);
  }, []);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [pageResult, deleted] = await Promise.all([
        fetchAdminCatalogProductsPage({
          page,
          perPage: PAGE_SIZE,
          search: debouncedSearch || undefined,
          departmentId: filterDepartmentId || undefined,
          categoryId: filterCategoryId || undefined,
          subcategoryId: filterSubcategoryId || undefined,
          catalogProductTypeId: filterTypeId || undefined,
          brandId: filterBrandId || undefined,
          status: statusFilter === "all" ? undefined : statusFilter,
          featured:
            featuredFilter === "featured"
              ? true
              : featuredFilter === "standard"
                ? false
                : undefined,
        }),
        fetchAdminCatalogProductsPage({ trashed: true, perPage: 100 }),
      ]);
      setProducts(pageResult.items);
      setTotal(pageResult.total);
      setLastPage(pageResult.lastPage);
      setTrashed(deleted.items);
    } catch (err) {
      setProducts([]);
      setTrashed([]);
      setTotal(0);
      setLastPage(1);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load products from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [
    page,
    debouncedSearch,
    filterDepartmentId,
    filterCategoryId,
    filterSubcategoryId,
    filterTypeId,
    filterBrandId,
    statusFilter,
    featuredFilter,
  ]);

  useEffect(() => {
    void reloadLookups().catch(() => {
      setError("Unable to load catalog lookups.");
    });
  }, [reloadLookups]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const rootCategories = useMemo(
    () => categories.filter((item) => !item.parentId),
    [categories],
  );

  const filterCategories = useMemo(() => {
    return rootCategories.filter(
      (item) => !filterDepartmentId || item.departmentId === filterDepartmentId,
    );
  }, [rootCategories, filterDepartmentId]);

  const filterSubcategories = useMemo(() => {
    return categories.filter(
      (item) =>
        item.parentId &&
        (!filterCategoryId || item.parentId === filterCategoryId) &&
        (!filterDepartmentId || item.departmentId === filterDepartmentId),
    );
  }, [categories, filterCategoryId, filterDepartmentId]);

  const filterTypes = useMemo(() => {
    return productTypes.filter((item) => {
      if (filterSubcategoryId) {
        return productTypeMatchesCategoryScope(
          item.subcategoryId,
          filterSubcategoryId,
          categories,
        );
      }
      if (filterCategoryId) {
        const childIds = categories
          .filter((category) => category.parentId === filterCategoryId)
          .map((category) => category.id);
        return childIds.includes(item.subcategoryId) || item.subcategoryId === filterCategoryId;
      }
      if (filterDepartmentId) {
        return categories.some(
          (category) =>
            category.id === item.subcategoryId && category.departmentId === filterDepartmentId,
        );
      }
      return true;
    });
  }, [productTypes, filterSubcategoryId, filterCategoryId, filterDepartmentId, categories]);

  const formCategories = useMemo(() => {
    if (!form) return [];
    return rootCategories.filter(
      (item) => !form.departmentId || item.departmentId === form.departmentId,
    );
  }, [form, rootCategories]);

  const formSubcategories = useMemo(() => {
    if (!form) return [];
    return categories.filter(
      (item) => item.parentId && (!form.categoryId || item.parentId === form.categoryId),
    );
  }, [form, categories]);

  const formTypes = useMemo(() => {
    if (!form) return [];
    return productTypes.filter((item) => {
      if (form.subcategoryId) {
        return productTypeMatchesCategoryScope(
          item.subcategoryId,
          form.subcategoryId,
          categories,
        );
      }
      if (form.categoryId) {
        const childIds = categories
          .filter((category) => category.parentId === form.categoryId)
          .map((category) => category.id);
        return childIds.includes(item.subcategoryId) || item.subcategoryId === form.categoryId;
      }
      return false;
    });
  }, [form, productTypes, categories]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("create") !== "1") {
      return;
    }

    setActionError(null);
    setFormTab("details");
    setForm({
      ...emptyForm(),
      sortOrder: total + 1,
    });
    window.history.replaceState({}, "", "/admin/products");
  }, [total]);

  const openCreate = () => {
    setActionError(null);
    setFormTab("details");
    setForm({
      ...emptyForm(),
      sortOrder: total + 1,
    });
  };

  const openEdit = useCallback(
    (
      product: AdminCatalogProduct,
      tab: "details" | "media" | "specifications" | "variants" | "shipping" | "stock" = "details",
    ) => {
      setFormTab(tab);
      const type = productTypes.find((item) => item.id === product.catalogProductTypeId);
      const subcategory = categories.find(
        (item) => item.id === (type?.subcategoryId ?? product.categoryId),
      );
      const category = subcategory?.parentId
        ? categories.find((item) => item.id === subcategory.parentId)
        : subcategory;
      setActionError(null);
      setForm({
        id: product.id,
        name: product.name,
        sku: product.sku ?? "",
        price: product.price,
        shortDescription: product.shortDescription,
        description: product.description,
        commerceJourney: "",
        storeId: product.storeId ?? "",
        departmentId: product.departmentId ?? category?.departmentId ?? "",
        categoryId: category?.id ?? "",
        subcategoryId: subcategory?.id ?? product.categoryId ?? "",
        catalogProductTypeId: product.catalogProductTypeId ?? "",
        brandId: product.brandId ?? "",
        status:
          product.status === "active" || product.status === "archived" ? product.status : "draft",
        visibility: product.visibility,
        sortOrder: product.sortOrder,
        isFeatured: product.isFeatured,
      });
    },
    [categories, productTypes],
  );

  useEffect(() => {
    if (!form?.id) {
      setPublishContext(null);
      setPublishVariants([]);
      setPublishShippingOptions(null);
      return;
    }

    let cancelled = false;

    void (async () => {
      try {
        const [product, variantsPayload] = await Promise.all([
          fetchAdminCatalogProduct(form.id!),
          fetchAdminProductVariants(form.id!),
        ]);

        if (cancelled) {
          return;
        }

        setPublishContext(product);
        setPublishVariants(variantsPayload.variants);
      } catch {
        if (!cancelled) {
          setPublishContext(null);
          setPublishVariants([]);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [form?.id, formTab]);

  useEffect(() => {
    if (!form?.id || !publishContext?.storeId || form.storeId.trim()) {
      return;
    }

    if (
      !shouldShowProductStoreSelector({
        isNewProduct: false,
        commerceJourney: "",
        commerceChannelCode: publishContext.commerceChannelCode,
      })
    ) {
      return;
    }

    setForm((previous) =>
      previous ? { ...previous, storeId: publishContext.storeId ?? "" } : previous,
    );
  }, [form?.id, form?.storeId, publishContext?.storeId, publishContext?.commerceChannelCode]);

  useEffect(() => {
    if (!form?.id) {
      setPublishShippingOptions(null);
      return;
    }

    const commerceChannelCode = publishContext?.commerceChannelCode ?? null;
    if (!isChinaImportCommerceChannel(commerceChannelCode)) {
      setPublishShippingOptions(null);
      return;
    }

    let cancelled = false;

    void (async () => {
      try {
        const options = await fetchAdminProductShippingOptions(form.id!);
        if (!cancelled) {
          setPublishShippingOptions(options);
        }
      } catch {
        if (!cancelled) {
          setPublishShippingOptions([]);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [form?.id, publishContext?.commerceChannelCode]);

  const showProductStoreSelector = useMemo(() => {
    if (!form) {
      return false;
    }

    return shouldShowProductStoreSelector({
      isNewProduct: !form.id,
      commerceJourney: form.commerceJourney,
      commerceChannelCode: publishContext?.commerceChannelCode ?? null,
    });
  }, [form, publishContext?.commerceChannelCode]);

  const publishReadiness = useMemo(() => {
    if (!form) {
      return null;
    }

    const selectedType = productTypes.find((item) => item.id === form.catalogProductTypeId);
    const subcategoryId = form.subcategoryId || selectedType?.subcategoryId || "";
    const commerceChannelCode = form.id
      ? publishContext?.commerceChannelCode ?? null
      : form.commerceJourney === "tz"
        ? "TZ_LOCAL"
        : form.commerceJourney === "china"
          ? "CHINA_IMPORT"
          : null;

    return calculateProductPublishReadiness({
      catalogProductTypeId: form.catalogProductTypeId,
      subcategoryId,
      catalogProductTypeSubcategoryId: selectedType?.subcategoryId ?? null,
      catalogProductTypeIsActive: selectedType?.isActive ?? true,
      isLeafCategory: isLeafCategoryId(subcategoryId, categories),
      price: form.price,
      commerceChannelCode,
      commerceChannelId: publishContext?.commerceChannelId ?? null,
      storeId: resolveProductStoreIdForReadiness({
        formStoreId: form.storeId,
        publishContextStoreId: publishContext?.storeId ?? null,
        commerceChannelCode,
        commerceJourney: form.commerceJourney,
        isNewProduct: !form.id,
      }),
      hasSimpleInventoryPolicy: publishContext?.hasSimpleInventoryPolicy ?? false,
      variants: publishVariants,
      isDemo: publishContext?.isDemo ?? false,
      hasPublishableShippingOption: isChinaImportCommerceChannel(commerceChannelCode)
        ? hasPublishableShippingOption(publishShippingOptions ?? [])
        : undefined,
    });
  }, [categories, form, productTypes, publishContext, publishShippingOptions, publishVariants]);

  const showShippingTab = Boolean(
    form?.id && isChinaImportCommerceChannel(publishContext?.commerceChannelCode),
  );
  const showStockTab = Boolean(form?.id && publishVariants.length === 0);

  useEffect(() => {
    if (formTab === "shipping" && !showShippingTab) {
      setFormTab("details");
    }
  }, [formTab, showShippingTab]);

  useEffect(() => {
    if (formTab === "stock" && !showStockTab) {
      setFormTab("details");
    }
  }, [formTab, showStockTab]);

  const refreshPublishShippingOptions = useCallback(async () => {
    if (!form?.id || !isChinaImportCommerceChannel(publishContext?.commerceChannelCode)) {
      setPublishShippingOptions(null);
      return;
    }

    try {
      const options = await fetchAdminProductShippingOptions(form.id);
      setPublishShippingOptions(options);
    } catch {
      setPublishShippingOptions([]);
    }
  }, [form?.id, publishContext?.commerceChannelCode]);

  const refreshPublishContext = useCallback(async () => {
    if (!form?.id) {
      return;
    }

    try {
      const [product, variantsPayload] = await Promise.all([
        fetchAdminCatalogProduct(form.id),
        fetchAdminProductVariants(form.id),
      ]);
      setPublishContext(product);
      setPublishVariants(variantsPayload.variants);
      if (isChinaImportCommerceChannel(product.commerceChannelCode)) {
        try {
          const options = await fetchAdminProductShippingOptions(form.id);
          setPublishShippingOptions(options);
        } catch {
          setPublishShippingOptions([]);
        }
      } else {
        setPublishShippingOptions(null);
      }
    } catch {
      // Keep existing publish context if refresh fails.
    }
  }, [form?.id]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const editId = params.get("edit")?.trim();
    if (!editId) {
      return;
    }
    if (deepLinkEditHandledRef.current === editId) {
      return;
    }

    const tab = parseCatalogProductEditTab(params.get("tab"));

    const openDeepLinkEdit = async () => {
      setActionError(null);

      try {
        const fromList =
          products.find((item) => item.id === editId) ??
          trashed.find((item) => item.id === editId);
        const product = fromList ?? (await fetchAdminCatalogProduct(editId));
        openEdit(product, tab);
        deepLinkEditHandledRef.current = editId;
        window.history.replaceState({}, "", "/admin/products");
      } catch (err) {
        setActionError(
          err instanceof AdminCatalogApiError
            ? err.message
            : "Unable to open product for editing.",
        );
        window.history.replaceState({}, "", "/admin/products");
      }
    };

    void openDeepLinkEdit();
  }, [openEdit, products, trashed]);

  const handleDelete = async (product: AdminCatalogProduct) => {
    if (!window.confirm(`Delete product “${product.name}”? You can restore it later.`)) {
      return;
    }
    setActionError(null);
    try {
      await deleteAdminCatalogProduct(product.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to delete product.",
      );
    }
  };

  const handleRestore = async (product: AdminCatalogProduct) => {
    setActionError(null);
    try {
      await restoreAdminCatalogProduct(product.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to restore product.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Product name is required.");
      return;
    }
    if (!form.catalogProductTypeId) {
      setActionError("Catalog product type is required.");
      return;
    }

    if (form.id && form.status === "active" && publishReadiness && !publishReadiness.ready) {
      setActionError(
        `Cannot activate yet. Missing: ${formatPublishReadinessMissingLabels(publishReadiness)}.`,
      );
      return;
    }

    if (!form.id && form.status !== "draft") {
      setActionError(
        "New products must be created as draft. Complete Media, Specs, Variants, and Pricing before activating.",
      );
      return;
    }

    let createChannelId: string | null = null;

    if (!form.id) {
      if (form.commerceJourney !== "china" && form.commerceJourney !== "tz") {
        setActionError("Select a commerce journey.");
        return;
      }

      createChannelId = resolveAdminCommerceChannelId(
        commerceChannels,
        commerceJourneyToChannelCode(form.commerceJourney),
      );
      if (!createChannelId) {
        setActionError("Unable to resolve commerce channel. Refresh and try again.");
        return;
      }
    }

    const storeValidationError = validateProductStoreAssignment({
      isNewProduct: !form.id,
      commerceJourney: form.commerceJourney,
      commerceChannelCode: publishContext?.commerceChannelCode ?? null,
      storeId: form.storeId,
    });
    if (storeValidationError) {
      setActionError(storeValidationError);
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = mergeProductStoreIdIntoPayload(
      {
        name: form.name.trim(),
        catalog_product_type_id: form.catalogProductTypeId,
        brand_id: form.brandId || null,
        sku: form.sku.trim() || null,
        price: form.price,
        short_description: form.shortDescription.trim() || null,
        description: form.description.trim() || null,
        status: form.id ? form.status : "draft",
        visibility: form.visibility,
        is_featured: form.isFeatured,
        sort_order: form.sortOrder,
        ...(!form.id
          ? {
              commerce_channel_id: createChannelId,
            }
          : {}),
      },
      {
        isNewProduct: !form.id,
        commerceJourney: form.commerceJourney,
        commerceChannelCode: publishContext?.commerceChannelCode ?? null,
        storeId: form.storeId,
      },
    );

    try {
      if (form.id) {
        await updateAdminCatalogProduct(form.id, payload);
        setForm(null);
      } else {
        const created = await createAdminCatalogProduct(payload);
        setForm({ ...form, id: created.id });
        setFormTab("media");
      }
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save product.",
      );
    } finally {
      setSaving(false);
    }
  };

  const currentPage = Math.min(page, Math.max(1, lastPage));

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Products</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Product Core, Media, Specifications, Variants, Stock, and Shipping (Pricing + Inventory engines).
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="admin-btn-secondary"
            onClick={() => setShowTrashed((value) => !value)}
          >
            {showTrashed ? "Hide trash" : `Trash (${trashed.length})`}
          </button>
          <button type="button" className="admin-btn-primary" onClick={openCreate}>
            Add product
          </button>
        </div>
      </div>

      {actionError ? (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {actionError}
        </div>
      ) : null}

      {form ? (
        <div className="admin-card mb-4 p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-sm font-semibold text-zinc-900">
              {form.id ? "Edit product" : "New product"}
            </h2>
            {form.id ? (
              <div className="flex gap-1 rounded-lg border border-zinc-200 p-0.5">
                <button
                  type="button"
                  className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                    formTab === "details"
                      ? "bg-zinc-900 text-white"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                  onClick={() => setFormTab("details")}
                >
                  Details
                </button>
                <button
                  type="button"
                  className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                    formTab === "media"
                      ? "bg-zinc-900 text-white"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                  onClick={() => setFormTab("media")}
                >
                  Media
                </button>
                <button
                  type="button"
                  className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                    formTab === "specifications"
                      ? "bg-zinc-900 text-white"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                  onClick={() => setFormTab("specifications")}
                >
                  Specifications
                </button>
                <button
                  type="button"
                  className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                    formTab === "variants"
                      ? "bg-zinc-900 text-white"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                  onClick={() => setFormTab("variants")}
                >
                  Variants
                </button>
                {showStockTab ? (
                  <button
                    type="button"
                    className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                      formTab === "stock"
                        ? "bg-zinc-900 text-white"
                        : "text-zinc-600 hover:bg-zinc-50"
                    }`}
                    onClick={() => setFormTab("stock")}
                  >
                    Stock
                  </button>
                ) : null}
                {showShippingTab ? (
                  <button
                    type="button"
                    className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                      formTab === "shipping"
                        ? "bg-zinc-900 text-white"
                        : "text-zinc-600 hover:bg-zinc-50"
                    }`}
                    onClick={() => setFormTab("shipping")}
                  >
                    Shipping
                  </button>
                ) : null}
              </div>
            ) : null}
          </div>

          {form.id && publishContext?.legacyConfigurationProduct ? (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              <p className="font-medium">Legacy configuration product</p>
              <p className="mt-1 text-xs text-amber-800">
                This product has sellable rows from the legacy Configuration Template engine
                (product attributes and dependency rules). Continue using the legacy product editor
                for configuration grids, MOQ tiers, and per-configuration stock until migration is
                complete. Redirect is not enabled yet.
              </p>
            </div>
          ) : null}

          {form.id && formTab === "media" ? (
            <div className="mt-4">
              <ProductMediaManager productId={form.id} productName={form.name || "Product"} />
              <div className="mt-4">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => setForm(null)}
                >
                  Close
                </button>
              </div>
            </div>
          ) : form.id && formTab === "specifications" ? (
            <div className="mt-4">
              <ProductSpecificationsManager productId={form.id} />
              <div className="mt-4">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => setForm(null)}
                >
                  Close
                </button>
              </div>
            </div>
          ) : form.id && formTab === "variants" ? (
            <div className="mt-4">
              <ProductVariantsManager productId={form.id} />
              <div className="mt-4">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => setForm(null)}
                >
                  Close
                </button>
              </div>
            </div>
          ) : form.id && formTab === "stock" && showStockTab ? (
            <div className="mt-4">
              <ProductStockManager
                productId={form.id}
                onStockSaved={() => {
                  void refreshPublishContext();
                }}
              />
              <div className="mt-4">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => setForm(null)}
                >
                  Close
                </button>
              </div>
            </div>
          ) : form.id && formTab === "shipping" && showShippingTab ? (
            <div className="mt-4">
              <ProductShippingManager
                productId={form.id}
                onSaved={() => {
                  void refreshPublishShippingOptions();
                }}
              />
              <div className="mt-4">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => setForm(null)}
                >
                  Close
                </button>
              </div>
            </div>
          ) : (
          <>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            {!form.id ? (
              <>
                <div className="sm:col-span-2">
                  <label className="admin-label" htmlFor="product-commerce-journey">
                    Commerce journey *
                  </label>
                  <select
                    id="product-commerce-journey"
                    className="admin-input mt-1.5"
                    value={form.commerceJourney}
                    onChange={(event) => {
                      const commerceJourney =
                        event.target.value === "tz"
                          ? "tz"
                          : event.target.value === "china"
                            ? "china"
                            : "";
                      setForm({
                        ...form,
                        commerceJourney,
                        storeId: commerceJourney === "tz" ? form.storeId : "",
                      });
                    }}
                  >
                    <option value="">Select commerce journey</option>
                    <option value="china">Order From China</option>
                    <option value="tz">Buy From Tanzania</option>
                  </select>
                </div>
              </>
            ) : null}
            {showProductStoreSelector ? (
              <div className="sm:col-span-2">
                <label className="admin-label" htmlFor="product-store">
                  Store *
                </label>
                <select
                  id="product-store"
                  className="admin-input mt-1.5"
                  value={form.storeId}
                  disabled={storesLoading}
                  onChange={(event) =>
                    setForm({ ...form, storeId: event.target.value })
                  }
                >
                  <option value="">
                    {storesLoading
                      ? "Loading stores…"
                      : storesError
                        ? "Unable to load stores"
                        : stores.length === 0
                          ? "No active stores available"
                          : "Select store"}
                  </option>
                  {stores.map((store) => (
                    <option key={store.id} value={store.id}>
                      {store.name}
                      {store.code ? ` (${store.code})` : ""}
                    </option>
                  ))}
                </select>
                {storesError ? (
                  <p className="mt-1 text-xs text-red-600">{storesError}</p>
                ) : null}
              </div>
            ) : null}
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="product-name">
                Name *
              </label>
              <input
                id="product-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="product-sku">
                SKU
              </label>
              <input
                id="product-sku"
                className="admin-input mt-1.5"
                value={form.sku}
                onChange={(event) => setForm({ ...form, sku: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="product-brand">
                Brand
              </label>
              <select
                id="product-brand"
                className="admin-input mt-1.5"
                value={form.brandId}
                onChange={(event) => setForm({ ...form, brandId: event.target.value })}
              >
                <option value="">Select brand</option>
                {brands.map((brand) => (
                  <option key={brand.id} value={brand.id}>
                    {brand.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="product-price">
                Base price (TZS)
              </label>
              <input
                id="product-price"
                type="number"
                min={0}
                step={1}
                className="admin-input mt-1.5"
                value={form.price || ""}
                onChange={(event) =>
                  setForm({ ...form, price: Number(event.target.value) || 0 })
                }
              />
              <p className="mt-1 text-xs text-zinc-500">
                Required for simple products before activation. Variant products use Variants tab
                pricing.
              </p>
            </div>
            <div>
              <label className="admin-label" htmlFor="form-department">
                Department *
              </label>
              <select
                id="form-department"
                className="admin-input mt-1.5"
                value={form.departmentId}
                onChange={(event) =>
                  setForm({
                    ...form,
                    departmentId: event.target.value,
                    categoryId: "",
                    subcategoryId: "",
                    catalogProductTypeId: "",
                  })
                }
              >
                <option value="">Select department</option>
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>
                    {department.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="form-category">
                Category *
              </label>
              <select
                id="form-category"
                className="admin-input mt-1.5"
                value={form.categoryId}
                disabled={!form.departmentId}
                onChange={(event) =>
                  setForm({
                    ...form,
                    categoryId: event.target.value,
                    subcategoryId: "",
                    catalogProductTypeId: "",
                  })
                }
              >
                <option value="">Select category</option>
                {formCategories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="form-subcategory">
                Subcategory *
              </label>
              <select
                id="form-subcategory"
                className="admin-input mt-1.5"
                value={form.subcategoryId}
                disabled={!form.categoryId}
                onChange={(event) =>
                  setForm({
                    ...form,
                    subcategoryId: event.target.value,
                    catalogProductTypeId: "",
                  })
                }
              >
                <option value="">Select subcategory</option>
                {formSubcategories.map((subcategory) => (
                  <option key={subcategory.id} value={subcategory.id}>
                    {subcategory.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="form-type">
                Product Type *
              </label>
              <select
                id="form-type"
                className="admin-input mt-1.5"
                value={form.catalogProductTypeId}
                disabled={!form.subcategoryId && !form.categoryId}
                onChange={(event) =>
                  setForm({ ...form, catalogProductTypeId: event.target.value })
                }
              >
                <option value="">Select product type</option>
                {formTypes.map((type) => (
                  <option key={type.id} value={type.id}>
                    {type.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="product-short">
                Short description
              </label>
              <input
                id="product-short"
                className="admin-input mt-1.5"
                value={form.shortDescription}
                onChange={(event) =>
                  setForm({ ...form, shortDescription: event.target.value })
                }
              />
            </div>
            <div className="sm:col-span-2">
              <label className="admin-label" htmlFor="product-description">
                Description
              </label>
              <textarea
                id="product-description"
                className="admin-input mt-1.5 min-h-[90px]"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="product-status">
                Lifecycle status
              </label>
              {form.id ? (
                <select
                  id="product-status"
                  className="admin-input mt-1.5"
                  value={form.status}
                  onChange={(event) => {
                    const nextStatus = event.target.value as ProductFormState["status"];
                    if (
                      nextStatus === "active" &&
                      publishReadiness &&
                      !publishReadiness.ready
                    ) {
                      setActionError(
                        `Cannot set Active yet. Missing: ${formatPublishReadinessMissingLabels(publishReadiness)}.`,
                      );
                      return;
                    }

                    setActionError(null);
                    setForm({
                      ...form,
                      status: nextStatus,
                    });
                  }}
                >
                  <option value="draft">Draft</option>
                  <option value="active">Active</option>
                  <option value="archived">Archived</option>
                </select>
              ) : (
                <>
                  <input
                    id="product-status"
                    className="admin-input mt-1.5 bg-zinc-50 text-zinc-600"
                    value="Draft"
                    readOnly
                    disabled
                  />
                  <p className="mt-1 text-xs text-zinc-500">
                    New products start as draft. Set Active after Media, Specs, Variants, and Pricing.
                  </p>
                </>
              )}
              {form.id ? (
                <p className="mt-1 text-xs text-zinc-500">
                  Lifecycle status controls listing and activation. Use Draft, Active, or Archived.
                </p>
              ) : null}
            </div>
            <div>
              <label className="admin-label" htmlFor="product-visibility">
                Visibility
              </label>
              <select
                id="product-visibility"
                className="admin-input mt-1.5"
                value={form.visibility}
                onChange={(event) =>
                  setForm({
                    ...form,
                    visibility: event.target.value as ProductFormState["visibility"],
                  })
                }
              >
                <option value="public">Public</option>
                <option value="private">Private</option>
                <option value="hidden">Hidden</option>
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="product-sort">
                Sort order
              </label>
              <input
                id="product-sort"
                type="number"
                min={0}
                className="admin-input mt-1.5"
                value={form.sortOrder}
                onChange={(event) =>
                  setForm({ ...form, sortOrder: Number(event.target.value) || 0 })
                }
              />
            </div>
            <div className="flex flex-col justify-end gap-2 pb-1">
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isFeatured}
                  onChange={(event) => setForm({ ...form, isFeatured: event.target.checked })}
                />
                Featured
              </label>
            </div>
          </div>
          {publishReadiness ? (
            <div className="mt-4">
              <PublishReadinessChecklist
                readiness={publishReadiness}
                showWarning={form.status === "active" && !publishReadiness.ready}
              />
            </div>
          ) : null}
          <div className="mt-4 flex gap-2">
            <button
              type="button"
              className="admin-btn-primary"
              disabled={saving}
              onClick={() => void handleSave()}
            >
              {saving ? "Saving…" : form.id ? "Save details" : "Save & manage media"}
            </button>
            {form.id ? (
              <button
                type="button"
                className="admin-btn-secondary"
                disabled={saving}
                onClick={() => setFormTab("media")}
              >
                Media tab
              </button>
            ) : null}
            <button
              type="button"
              className="admin-btn-secondary"
              disabled={saving}
              onClick={() => setForm(null)}
            >
              Cancel
            </button>
          </div>
          </>
          )}
        </div>
      ) : null}

      <div className="admin-card overflow-hidden">
        <div className="flex flex-wrap gap-3 border-b border-zinc-200 px-4 py-3">
          <input
            type="search"
            className="admin-input min-w-[180px] flex-1"
            placeholder="Search products…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={filterDepartmentId}
            onChange={(event) => {
              setFilterDepartmentId(event.target.value);
              setFilterCategoryId("");
              setFilterSubcategoryId("");
              setFilterTypeId("");
            }}
          >
            <option value="">All departments</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
          <select
            className="admin-input w-auto"
            value={filterCategoryId}
            onChange={(event) => {
              setFilterCategoryId(event.target.value);
              setFilterSubcategoryId("");
              setFilterTypeId("");
            }}
          >
            <option value="">All categories</option>
            {filterCategories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
          <select
            className="admin-input w-auto"
            value={filterSubcategoryId}
            onChange={(event) => {
              setFilterSubcategoryId(event.target.value);
              setFilterTypeId("");
            }}
          >
            <option value="">All subcategories</option>
            {filterSubcategories.map((subcategory) => (
              <option key={subcategory.id} value={subcategory.id}>
                {subcategory.name}
              </option>
            ))}
          </select>
          <select
            className="admin-input w-auto"
            value={filterTypeId}
            onChange={(event) => setFilterTypeId(event.target.value)}
          >
            <option value="">All product types</option>
            {filterTypes.map((type) => (
              <option key={type.id} value={type.id}>
                {type.name}
              </option>
            ))}
          </select>
          <select
            className="admin-input w-auto"
            value={filterBrandId}
            onChange={(event) => setFilterBrandId(event.target.value)}
          >
            <option value="">All brands</option>
            {brands.map((brand) => (
              <option key={brand.id} value={brand.id}>
                {brand.name}
              </option>
            ))}
          </select>
          <select
            className="admin-input w-auto"
            value={statusFilter}
            onChange={(event) =>
              setStatusFilter(event.target.value as typeof statusFilter)
            }
          >
            <option value="all">All statuses</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
          <select
            className="admin-input w-auto"
            value={featuredFilter}
            onChange={(event) =>
              setFeaturedFilter(event.target.value as typeof featuredFilter)
            }
          >
            <option value="all">All</option>
            <option value="featured">Featured</option>
            <option value="standard">Not featured</option>
          </select>
        </div>

        {isLoading ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">Loading products…</div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load products</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : products.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No products configured.
          </div>
        ) : (
          <>
            <ul className="divide-y divide-zinc-100">
              {products.map((product) => (
                <li key={product.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {product.name}
                      <span
                        className={`ml-2 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase ${
                          product.status === "active"
                            ? "bg-emerald-50 text-emerald-700"
                            : product.status === "archived"
                              ? "bg-zinc-100 text-zinc-500"
                              : "bg-amber-50 text-amber-700"
                        }`}
                      >
                        {product.status}
                      </span>
                      {product.isFeatured ? (
                        <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                          Featured
                        </span>
                      ) : null}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {product.slug}
                      {product.sku ? ` · ${product.sku}` : ""}
                      {product.brandName ? ` · ${product.brandName}` : ""}
                      {product.catalogProductTypeName
                        ? ` · ${product.catalogProductTypeName}`
                        : ""}
                    </p>
                    {product.shortDescription ? (
                      <p className="mt-1 text-xs text-zinc-600">{product.shortDescription}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(product)}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(product, "media")}
                    >
                      Media
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(product, "specifications")}
                    >
                      Specs
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                      onClick={() => openEdit(product, "variants")}
                    >
                      Variants
                    </button>
                    <button
                      type="button"
                      className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      onClick={() => void handleDelete(product)}
                    >
                      Delete
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-4 py-3">
              <p className="text-xs text-zinc-500">
                Showing page {currentPage} of {lastPage} · {total} products
              </p>
              <div className="flex gap-2">
                <button
                  type="button"
                  className="admin-btn-secondary"
                  disabled={currentPage <= 1}
                  onClick={() => setPage((value) => Math.max(1, value - 1))}
                >
                  Previous
                </button>
                <button
                  type="button"
                  className="admin-btn-secondary"
                  disabled={currentPage >= lastPage}
                  onClick={() => setPage((value) => Math.min(lastPage, value + 1))}
                >
                  Next
                </button>
              </div>
            </div>
          </>
        )}
      </div>

      {showTrashed ? (
        <div className="admin-card mt-4 overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-3">
            <h2 className="text-sm font-semibold text-zinc-900">Deleted products</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((product) => (
                <li key={product.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{product.name}</p>
                    <p className="text-xs text-zinc-500">{product.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(product)}
                  >
                    Restore
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}
