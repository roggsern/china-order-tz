"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { parseCatalogProductEditTab, type CatalogProductEditTab } from "@/lib/admin/product-id-map";
import {
  calculateProductPublishReadiness,
  formatPublishReadinessMissingLabels,
  isLeafCategoryId,
  isSellableVariant,
} from "@/lib/admin/product-publish-readiness";
import { isChinaImportCommerceChannel, hasPublishableShippingOption } from "@/lib/admin/product-shipping-sync";
import {
  mergeProductStoreIdIntoPayload,
  resolveProductStoreIdForReadiness,
  shouldShowProductStoreSelector,
  validateProductStoreAssignment,
} from "@/lib/admin/product-store-assignment";
import {
  mergeProductSupplierIdIntoPayload,
  resolveProductSupplierIdForReadiness,
  shouldShowProductSupplierSelector,
  validateProductSupplierAssignment,
} from "@/lib/admin/product-supplier-assignment";
import { fetchAdminSuppliers, type AdminSupplier } from "@/lib/api/admin-procurement";
import { PublishReadinessChecklist } from "@/components/admin/PublishReadinessChecklist";
import { AdminProductBulkActionBar } from "@/components/admin/AdminProductBulkActionBar";
import { AdminProductCreationWizard } from "@/components/admin/AdminProductCreationWizard";
import { AdminBrandAsyncSelect } from "@/components/admin/AdminBrandAsyncSelect";
import { AdminCategoryTreeSelect } from "@/components/admin/AdminCategoryTreeSelect";
import { AdminSupplierAsyncSelect } from "@/components/admin/AdminSupplierAsyncSelect";
import { ProductForceDeleteDialog } from "@/components/admin/ProductForceDeleteDialog";
import { ProductMediaManager } from "@/components/admin/ProductMediaManager";
import { ProductShippingManager } from "@/components/admin/ProductShippingManager";
import { ProductCommercialAvailabilityManager } from "@/components/admin/ProductCommercialAvailabilityManager";
import { ProductSimplePricingFields } from "@/components/admin/ProductSimplePricingFields";
import { ProductStockManager } from "@/components/admin/ProductStockManager";
import { ProductSpecificationsManager } from "@/components/admin/ProductSpecificationsManager";
import { ProductVariantsManager } from "@/components/admin/ProductVariantsManager";
import {
  adminProductThumbnailUrl,
  formatAdminChannelBadge,
  formatAdminPriceRange,
  formatAdminStockSummary,
} from "@/lib/admin/admin-product-list";
import {
  clearTableSelection,
  createEmptySelection,
  pruneSelectionToVisible,
  resolveTableSelectionState,
  toggleSelectAllVisible,
  toggleTableSelection,
} from "@/lib/admin/table-selection";
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
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { hasAdminPermission } from "@/lib/api/admin-me";
import { resolveBrandLeafCategoryId } from "@/lib/admin/catalog-selector-utils";
import { productTypeMatchesCategoryScope } from "@/lib/admin/catalog-product-type-scope";
import {
  mapWizardPricingModelToApi,
  shouldUseProductCreationWizard,
  wizardSavePricingFields,
  type ProductCreationPricingModel,
} from "@/lib/admin/product-creation-wizard";

type CatalogProductsView = "active" | "deleted";

function parseCatalogProductsView(raw: string | null): CatalogProductsView {
  return raw === "deleted" ? "deleted" : "active";
}
type ProductFormState = {
  id?: string;
  name: string;
  sku: string;
  price: number;
  costPrice: number | null;
  shortDescription: string;
  description: string;
  commerceJourney: CommerceJourney | "";
  pricingModel: ProductCreationPricingModel;
  storeId: string;
  departmentId: string;
  categoryId: string;
  subcategoryId: string;
  catalogProductTypeId: string;
  productCondition: string;
  brandId: string;
  supplierId: string;
  status: "draft" | "active" | "archived";
  visibility: "public" | "private" | "hidden";
  sortOrder: number;
  isFeatured: boolean;
};

const emptyForm = (): ProductFormState => ({
  name: "",
  sku: "",
  price: 0,
  costPrice: null,
  shortDescription: "",
  description: "",
  commerceJourney: "",
  pricingModel: "simple",
  storeId: "",
  departmentId: "",
  categoryId: "",
  subcategoryId: "",
  catalogProductTypeId: "",
  productCondition: "",
  brandId: "",
  supplierId: "",
  status: "draft",
  visibility: "public",
  sortOrder: 0,
  isFeatured: false,
});

const PAGE_SIZE = 15;

export function AdminCatalogProductsPanel() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const catalogView = parseCatalogProductsView(searchParams.get("view"));
  const { permissions } = useAdminPermissions();
  const canCreateBrand = hasAdminPermission(permissions, "catalog.create");
  const canForceDelete = hasAdminPermission(permissions, "catalog.force_delete");
  const [products, setProducts] = useState<AdminCatalogProduct[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => createEmptySelection());
  const [activeTotal, setActiveTotal] = useState(0);
  const [deletedTotal, setDeletedTotal] = useState(0);
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [productTypes, setProductTypes] = useState<AdminCatalogProductType[]>([]);
  const [brands, setBrands] = useState<AdminBrand[]>([]);
  const [suppliers, setSuppliers] = useState<AdminSupplier[]>([]);
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
  const [actionNotice, setActionNotice] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [filterDepartmentId, setFilterDepartmentId] = useState("");
  const [filterCategoryId, setFilterCategoryId] = useState("");
  const [filterSubcategoryId, setFilterSubcategoryId] = useState("");
  const [filterTypeId, setFilterTypeId] = useState("");
  const [filterBrandId, setFilterBrandId] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | "draft" | "active" | "archived">("all");
  const [featuredFilter, setFeaturedFilter] = useState<"all" | "featured" | "standard">("all");
  const [forceDeleteTarget, setForceDeleteTarget] = useState<AdminCatalogProduct | null>(null);
  const [form, setForm] = useState<ProductFormState | null>(null);
  const [formTab, setFormTab] = useState<CatalogProductEditTab>("details");
  const [saving, setSaving] = useState(false);
  const [publishVariants, setPublishVariants] = useState<AdminProductVariant[]>([]);
  const [publishContextRefreshing, setPublishContextRefreshing] = useState(false);
  const [publishRefreshError, setPublishRefreshError] = useState<string | null>(null);
  const [publishContext, setPublishContext] = useState<AdminCatalogProduct | null>(null);
  const [publishShippingOptions, setPublishShippingOptions] = useState<
    AdminProductShippingOption[] | null
  >(null);
  const deepLinkEditHandledRef = useRef<string | null>(null);

  const setCatalogView = useCallback(
    (view: CatalogProductsView) => {
      const params = new URLSearchParams(searchParams.toString());
      if (view === "active") {
        params.delete("view");
      } else {
        params.set("view", "deleted");
      }
      const query = params.toString();
      router.replace(query ? `${pathname}?${query}` : pathname, { scroll: false });
      setPage(1);
      setSelectedIds(createEmptySelection());
    },
    [pathname, router, searchParams],
  );

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
    catalogView,
  ]);

  const reloadLookups = useCallback(async () => {
    setStoresLoading(true);
    setStoresError(null);
    const [nextDepartments, nextCategories, nextTypes, nextBrands, nextSuppliers, nextChannels, nextStores] =
      await Promise.all([
        fetchAdminDepartments(),
        fetchAdminCategories(),
        fetchAdminCatalogProductTypes(),
        fetchAdminBrands(),
        fetchAdminSuppliers({ isActive: true }).catch(() => [] as AdminSupplier[]),
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
    setSuppliers(nextSuppliers);
    setCommerceChannels(nextChannels);
    setStores(nextStores);
    setStoresLoading(false);
  }, []);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const listParams = {
        page,
        perPage: PAGE_SIZE,
        search: debouncedSearch || undefined,
        departmentId: filterDepartmentId || undefined,
        categoryId: filterCategoryId || undefined,
        subcategoryId: filterSubcategoryId || undefined,
        catalogProductTypeId: filterTypeId || undefined,
        brandId: filterBrandId || undefined,
        status:
          catalogView === "active" && statusFilter !== "all" ? statusFilter : undefined,
        featured:
          catalogView === "active"
            ? featuredFilter === "featured"
              ? true
              : featuredFilter === "standard"
                ? false
                : undefined
            : undefined,
        trashed: catalogView === "deleted",
      } as const;

      const [pageResult, oppositeBadge] = await Promise.all([
        fetchAdminCatalogProductsPage(listParams),
        fetchAdminCatalogProductsPage({
          trashed: catalogView !== "deleted",
          perPage: 1,
          page: 1,
        }),
      ]);

      setProducts(pageResult.items);
      setTotal(pageResult.total);
      setLastPage(pageResult.lastPage);
      if (catalogView === "deleted") {
        setDeletedTotal(pageResult.total);
        setActiveTotal(oppositeBadge.total);
      } else {
        setActiveTotal(pageResult.total);
        setDeletedTotal(oppositeBadge.total);
      }
    } catch (err) {
      setProducts([]);
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
    catalogView,
  ]);

  useEffect(() => {
    void reloadLookups().catch(() => {
      setError("Unable to load catalog lookups.");
    });
  }, [reloadLookups]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const visibleIds = useMemo(() => products.map((product) => product.id), [products]);
  const selection = resolveTableSelectionState(selectedIds, visibleIds);

  useEffect(() => {
    setSelectedIds((current) => pruneSelectionToVisible(current, visibleIds));
  }, [visibleIds]);

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
      tab: CatalogProductEditTab = "details",
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
        costPrice: product.costPrice,
        shortDescription: product.shortDescription,
        description: product.description,
        commerceJourney:
          product.commerceChannelCode === "TZ_LOCAL"
            ? "tz"
            : product.commerceChannelCode === "CHINA_IMPORT"
              ? "china"
              : "",
        pricingModel: product.pricingModel,
        storeId: product.storeId ?? "",
        departmentId: product.departmentId ?? category?.departmentId ?? "",
        categoryId: category?.id ?? "",
        subcategoryId: subcategory?.id ?? product.categoryId ?? "",
        catalogProductTypeId: product.catalogProductTypeId ?? "",
        productCondition: product.productCondition ?? "",
        brandId: product.brandId ?? "",
        supplierId: product.supplierId ?? "",
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
    if (!form?.id || !publishContext?.supplierId || form.supplierId.trim()) {
      return;
    }

    if (
      !shouldShowProductSupplierSelector({
        isNewProduct: false,
        commerceJourney: "",
        commerceChannelCode: publishContext.commerceChannelCode,
      })
    ) {
      return;
    }

    setForm((previous) =>
      previous ? { ...previous, supplierId: publishContext.supplierId ?? "" } : previous,
    );
  }, [form?.id, form?.supplierId, publishContext?.supplierId, publishContext?.commerceChannelCode]);

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

  const showProductSupplierSelector = useMemo(() => {
    if (!form) {
      return false;
    }

    return shouldShowProductSupplierSelector({
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
      pricingModel: form.pricingModel === "variants" ? "variants" : "simple",
      commerceChannelCode,
      commerceChannelId: publishContext?.commerceChannelId ?? null,
      storeId: resolveProductStoreIdForReadiness({
        formStoreId: form.storeId,
        publishContextStoreId: publishContext?.storeId ?? null,
        commerceChannelCode,
        commerceJourney: form.commerceJourney,
        isNewProduct: !form.id,
      }),
      supplierId: resolveProductSupplierIdForReadiness({
        formSupplierId: form.supplierId,
        publishContextSupplierId: publishContext?.supplierId ?? null,
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
  const isChinaProduct = isChinaImportCommerceChannel(publishContext?.commerceChannelCode);
  const showStockTab = Boolean(form?.id && publishVariants.length === 0 && !isChinaProduct);
  const showCommercialAvailabilityTab = Boolean(form?.id && isChinaProduct);

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

  useEffect(() => {
    if (formTab === "commercial-availability" && !showCommercialAvailabilityTab) {
      setFormTab("details");
    }
  }, [formTab, showCommercialAvailabilityTab]);

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

    setPublishContextRefreshing(true);
    setPublishRefreshError(null);

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
    } catch (err) {
      setPublishRefreshError(
        err instanceof Error
          ? err.message
          : "Unable to refresh publish readiness. Saved pricing is kept — reopen Edit to retry.",
      );
      throw err instanceof Error ? err : new Error("Unable to refresh publish readiness.");
    } finally {
      setPublishContextRefreshing(false);
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
        const fromList = products.find((item) => item.id === editId);
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
  }, [openEdit, products]);

  const handleDelete = async (product: AdminCatalogProduct) => {
    if (!window.confirm(`Delete product “${product.name}”? You can restore it later.`)) {
      return;
    }
    setActionError(null);
    setActionNotice(null);
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
    setActionNotice(null);
    try {
      await restoreAdminCatalogProduct(product.id);
      setActionNotice(`Restored “${product.name}”.`);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to restore product.",
      );
    }
  };

  const useWizardFlow = Boolean(
    form &&
      shouldUseProductCreationWizard({
        isNewProduct: !form.id,
        status: form.status,
      }),
  );

  const saveProductDraft = async (options?: {
    strictStepValidation?: boolean;
  }): Promise<boolean> => {
    if (!form || !form.name.trim()) {
      setActionError("Product name is required.");
      return false;
    }
    if (!form.catalogProductTypeId) {
      setActionError("Catalog product type is required.");
      return false;
    }

    if (form.id && form.status === "active" && publishReadiness && !publishReadiness.ready) {
      setActionError(
        `Cannot activate yet. Missing: ${formatPublishReadinessMissingLabels(publishReadiness)}.`,
      );
      return false;
    }

    if (!form.id && form.status !== "draft") {
      setActionError(
        "New products must be created as draft. Complete Media, Specs, Variants, and Pricing before activating.",
      );
      return false;
    }

    let createChannelId: string | null = null;

    if (!form.id) {
      if (form.commerceJourney !== "china" && form.commerceJourney !== "tz") {
        setActionError("Select a commerce journey.");
        return false;
      }

      createChannelId = resolveAdminCommerceChannelId(
        commerceChannels,
        commerceJourneyToChannelCode(form.commerceJourney),
      );
      if (!createChannelId) {
        setActionError("Unable to resolve commerce channel. Refresh and try again.");
        return false;
      }
    }

    const requireChannelFields = options?.strictStepValidation !== false && !useWizardFlow;

    const storeValidationError = validateProductStoreAssignment({
      isNewProduct: !form.id,
      commerceJourney: form.commerceJourney,
      commerceChannelCode: publishContext?.commerceChannelCode ?? null,
      storeId: form.storeId,
      requireAssignment: requireChannelFields,
    });
    if (storeValidationError) {
      setActionError(storeValidationError);
      return false;
    }

    const supplierValidationError = validateProductSupplierAssignment({
      isNewProduct: !form.id,
      commerceJourney: form.commerceJourney,
      commerceChannelCode: publishContext?.commerceChannelCode ?? null,
      supplierId: form.supplierId,
      requireAssignment: requireChannelFields,
    });
    if (supplierValidationError) {
      setActionError(supplierValidationError);
      return false;
    }

    setSaving(true);
    setActionError(null);

    const pricingFields = useWizardFlow
      ? wizardSavePricingFields(form.pricingModel, form.price, form.costPrice)
      : { price: form.price, cost_price: form.costPrice };

    const payload = mergeProductSupplierIdIntoPayload(
      mergeProductStoreIdIntoPayload(
        {
          name: form.name.trim(),
          catalog_product_type_id: form.catalogProductTypeId,
          product_condition: form.productCondition || null,
          brand_id: form.brandId || null,
          sku: form.sku.trim() || null,
          price: pricingFields.price,
          cost_price: pricingFields.cost_price,
          short_description: form.shortDescription.trim() || null,
          description: form.description.trim() || null,
          status: form.id ? form.status : "draft",
          visibility: form.visibility,
          is_featured: form.isFeatured,
          sort_order: form.sortOrder,
          ...(!form.id
            ? {
                commerce_channel_id: createChannelId,
                pricing_model: mapWizardPricingModelToApi(form.pricingModel),
              }
            : {}),
        },
        {
          isNewProduct: !form.id,
          commerceJourney: form.commerceJourney,
          commerceChannelCode: publishContext?.commerceChannelCode ?? null,
          storeId: form.storeId,
        },
      ),
      {
        isNewProduct: !form.id,
        commerceJourney: form.commerceJourney,
        commerceChannelCode: publishContext?.commerceChannelCode ?? null,
        supplierId: form.supplierId,
      },
    );

    try {
      if (form.id) {
        await updateAdminCatalogProduct(form.id, payload);
        if (!useWizardFlow) {
          setForm(null);
        }
      } else {
        const created = await createAdminCatalogProduct(payload);
        setForm({ ...form, id: created.id });
        if (!useWizardFlow) {
          setFormTab("media");
        }
      }
      await reload();
      return true;
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save product.",
      );
      return false;
    } finally {
      setSaving(false);
    }
  };

  const handleSave = async () => {
    await saveProductDraft({ strictStepValidation: true });
  };

  const handleWizardPublish = async () => {
    if (!form?.id) {
      setActionError("Save the product draft before publishing.");
      return;
    }
    if (!publishReadiness?.ready) {
      setActionError(
        `Cannot publish yet. Missing: ${formatPublishReadinessMissingLabels(publishReadiness!)}.`,
      );
      return;
    }

    setSaving(true);
    setActionError(null);
    try {
      const pricingFields = wizardSavePricingFields(form.pricingModel, form.price, form.costPrice);
      await updateAdminCatalogProduct(form.id, {
        name: form.name.trim(),
        catalog_product_type_id: form.catalogProductTypeId,
        product_condition: form.productCondition || null,
        brand_id: form.brandId || null,
        sku: form.sku.trim() || null,
        price: pricingFields.price,
        cost_price: pricingFields.cost_price,
        short_description: form.shortDescription.trim() || null,
        description: form.description.trim() || null,
        status: "active",
        visibility: form.visibility,
        is_featured: form.isFeatured,
        sort_order: form.sortOrder,
        ...mergeProductStoreIdIntoPayload(
          {},
          {
            isNewProduct: false,
            commerceJourney: form.commerceJourney,
            commerceChannelCode: publishContext?.commerceChannelCode ?? null,
            storeId: form.storeId,
          },
        ),
        ...mergeProductSupplierIdIntoPayload(
          {},
          {
            isNewProduct: false,
            commerceJourney: form.commerceJourney,
            commerceChannelCode: publishContext?.commerceChannelCode ?? null,
            supplierId: form.supplierId,
          },
        ),
      });
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to publish product.",
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
        {catalogView === "active" ? (
          <button type="button" className="admin-btn-primary" onClick={openCreate}>
            Add product
          </button>
        ) : null}
      </div>

      <div className="mb-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-3">
        <button
          type="button"
          className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
            catalogView === "active"
              ? "bg-zinc-900 text-white"
              : "bg-zinc-100 text-zinc-700 hover:bg-zinc-200"
          }`}
          onClick={() => setCatalogView("active")}
        >
          Active Products
          <span className="ml-2 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-semibold">
            {activeTotal}
          </span>
        </button>
        <button
          type="button"
          className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
            catalogView === "deleted"
              ? "bg-zinc-900 text-white"
              : "bg-zinc-100 text-zinc-700 hover:bg-zinc-200"
          }`}
          onClick={() => setCatalogView("deleted")}
        >
          Deleted Products
          <span className="ml-2 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-semibold">
            {deletedTotal}
          </span>
        </button>
      </div>

      {actionNotice ? (
        <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {actionNotice}
        </div>
      ) : null}

      {actionError ? (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {actionError}
        </div>
      ) : null}

      {forceDeleteTarget ? (
        <ProductForceDeleteDialog
          product={forceDeleteTarget}
          open
          onClose={() => setForceDeleteTarget(null)}
          onDeleted={(message) => {
            setActionNotice(message);
            void reload();
          }}
        />
      ) : null}

      {form ? (
        <div className="admin-card mb-4 p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-sm font-semibold text-zinc-900">
              {useWizardFlow ? "Create product (draft wizard)" : form.id ? "Edit product" : "New product"}
            </h2>
            {form.id && !useWizardFlow ? (
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
                {showCommercialAvailabilityTab ? (
                  <button
                    type="button"
                    className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                      formTab === "commercial-availability"
                        ? "bg-zinc-900 text-white"
                        : "text-zinc-600 hover:bg-zinc-50"
                    }`}
                    onClick={() => setFormTab("commercial-availability")}
                  >
                    Commercial Availability
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

          {useWizardFlow ? (
            <div className="mt-4">
              <AdminProductCreationWizard
                form={form}
                setForm={(updater) => setForm((current) => (current ? updater(current) : current))}
                departments={departments}
                categories={categories}
                productTypes={productTypes}
                brands={brands}
                suppliers={suppliers}
                stores={stores}
                storesLoading={storesLoading}
                storesError={storesError}
                canCreateBrand={canCreateBrand}
                saving={saving}
                actionError={actionError}
                publishReadiness={publishReadiness}
                publishVariantCount={publishVariants.length}
                publishSellableVariantCount={publishVariants.filter((variant) =>
                  isSellableVariant(variant, publishContext?.commerceChannelCode),
                ).length}
                hasPublishableShipping={hasPublishableShippingOption(publishShippingOptions ?? [])}
                onSaveDraft={saveProductDraft}
                onPublish={handleWizardPublish}
                onCancel={() => setForm(null)}
                onRefreshPublishContext={refreshPublishContext}
                publishContextRefreshing={publishContextRefreshing}
                publishRefreshError={publishRefreshError}
                onRefreshShipping={() => {
                  void refreshPublishShippingOptions();
                }}
              />
            </div>
          ) : form.id && formTab === "media" ? (
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
              <ProductVariantsManager
                productId={form.id}
                commerceChannelCode={publishContext?.commerceChannelCode ?? null}
                onVariantsChanged={refreshPublishContext}
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
          ) : form.id && formTab === "commercial-availability" && showCommercialAvailabilityTab ? (
            <div className="mt-4">
              <ProductCommercialAvailabilityManager
                productId={form.id}
                onSaved={() => {
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
              <AdminBrandAsyncSelect
                id="product-brand"
                value={form.brandId}
                selectedLabel={brands.find((brand) => brand.id === form.brandId)?.name}
                categoryId={resolveBrandLeafCategoryId(form.categoryId, form.subcategoryId)}
                canCreate={canCreateBrand}
                onChange={(brandId) => setForm({ ...form, brandId })}
              />
            </div>
            {showProductSupplierSelector ? (
              <div>
                <label className="admin-label" htmlFor="product-supplier">
                  Supplier *
                </label>
                <AdminSupplierAsyncSelect
                  id="product-supplier"
                  value={form.supplierId}
                  selectedLabel={
                    suppliers.find((supplier) => supplier.id === form.supplierId)?.name
                  }
                  onChange={(supplierId) => setForm({ ...form, supplierId })}
                />
                <p className="mt-1 text-xs text-zinc-500">
                  Required for China import procurement. Used when creating supplier purchase orders.
                </p>
              </div>
            ) : null}
            <div className="sm:col-span-2">
              <ProductSimplePricingFields
                sellingPrice={form.price}
                costPrice={form.costPrice}
                sellingPriceId="product-price"
                costPriceId="product-cost-price"
                onSellingPriceChange={(price) => setForm({ ...form, price })}
                onCostPriceChange={(costPrice) => setForm({ ...form, costPrice })}
              />
            </div>
            {isChinaProduct && form.id && publishVariants.length === 0 ? (
              <div className="sm:col-span-2">
                <ProductCommercialAvailabilityManager
                  productId={form.id}
                  onSaved={() => {
                    void refreshPublishContext();
                  }}
                />
              </div>
            ) : null}
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
              <AdminCategoryTreeSelect
                id="form-category"
                categories={categories}
                departmentId={form.departmentId}
                categoryId={form.categoryId}
                subcategoryId={form.subcategoryId}
                disabled={!form.departmentId}
                onChange={(selection) =>
                  setForm({
                    ...form,
                    categoryId: selection.categoryId,
                    subcategoryId: selection.subcategoryId,
                    catalogProductTypeId: "",
                  })
                }
              />
              {!form.departmentId ? (
                <p className="mt-1 text-xs text-zinc-500">Select a department first.</p>
              ) : (
                <p className="mt-1 text-xs text-zinc-500">
                  Search and pick a parent category or subcategory (shown as a tree).
                </p>
              )}
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
                onChange={(event) => {
                  const nextTypeId = event.target.value;
                  const nextType = formTypes.find((type) => type.id === nextTypeId);
                  setForm({
                    ...form,
                    catalogProductTypeId: nextTypeId,
                    productCondition: nextType?.supportsProductCondition
                      ? form.productCondition || "BRAND_NEW"
                      : "",
                  });
                }}
              >
                <option value="">Select product type</option>
                {formTypes.map((type) => (
                  <option key={type.id} value={type.id}>
                    {type.name}
                  </option>
                ))}
              </select>
            </div>
            {formTypes.find((type) => type.id === form.catalogProductTypeId)?.supportsProductCondition ? (
              <div className="sm:col-span-2">
                <p className="admin-label">Product Condition</p>
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                  {[
                    { value: "BRAND_NEW", label: "Brand New" },
                    { value: "OPEN_BOX", label: "Open Box" },
                    { value: "REFURBISHED", label: "Refurbished" },
                    { value: "USED", label: "Used / Second Hand" },
                  ].map((option) => (
                    <label
                      key={option.value}
                      className="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-800"
                    >
                      <input
                        type="radio"
                        name="form-product-condition"
                        value={option.value}
                        checked={(form.productCondition || "BRAND_NEW") === option.value}
                        onChange={() =>
                          setForm({ ...form, productCondition: option.value })
                        }
                      />
                      {option.label}
                    </label>
                  ))}
                </div>
              </div>
            ) : null}
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
                refreshing={publishContextRefreshing}
                refreshError={publishRefreshError}
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

      {catalogView === "active" ? (
        <AdminProductBulkActionBar
          selectedCount={selection.selectedCount}
          selectedIds={[...selectedIds]}
          permissions={permissions}
          onClearSelection={() => setSelectedIds(clearTableSelection())}
          onCompleted={() => {
            void reload();
          }}
        />
      ) : null}

      <div className="admin-card overflow-hidden">
        <div className="flex flex-wrap gap-3 border-b border-zinc-200 px-4 py-3">
          <input
            type="search"
            className="admin-input min-w-[180px] flex-1"
            placeholder={
              catalogView === "deleted" ? "Search deleted products…" : "Search products…"
            }
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
          {catalogView === "active" ? (
            <>
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
            </>
          ) : null}
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
            {catalogView === "deleted" ? "Trash is empty." : "No products configured."}
          </div>
        ) : (
          <>
            {catalogView === "active" ? (
              <div className="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/60 px-5 py-2">
                <input
                  type="checkbox"
                  checked={selection.allVisibleSelected}
                  onChange={() =>
                    setSelectedIds((current) => toggleSelectAllVisible(current, visibleIds))
                  }
                  aria-label="Select all products on this page"
                  className="h-4 w-4 rounded border-zinc-300 text-zinc-900"
                />
                <span className="text-xs text-zinc-500">
                  Select all on this page
                  {selection.selectedCount > 0
                    ? ` · ${selection.selectedCount} selected`
                    : ""}
                </span>
              </div>
            ) : null}
            <ul className="divide-y divide-zinc-100">
              {products.map((product) => {
                const thumb = adminProductThumbnailUrl(product.image);
                const channel = formatAdminChannelBadge(
                  product.commerceChannelCode,
                  product.commerceChannelLabel,
                );

                return (
                <li key={product.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                  {catalogView === "active" ? (
                    <input
                      type="checkbox"
                      checked={selectedIds.has(product.id)}
                      onChange={() =>
                        setSelectedIds((current) => toggleTableSelection(current, product.id))
                      }
                      aria-label={`Select product ${product.name}`}
                      className="mt-5 h-4 w-4 shrink-0 rounded border-zinc-300 text-zinc-900"
                    />
                  ) : null}
                  <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                    {thumb ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={thumb}
                        alt={product.image?.altText || product.name}
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      <div className="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase tracking-wide text-zinc-400">
                        No img
                      </div>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">
                      {product.name}
                      {catalogView === "active" ? (
                        <>
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
                          {channel ? (
                            <span
                              className={`ml-2 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase ${channel.className}`}
                            >
                              {channel.label}
                            </span>
                          ) : null}
                          {product.isFeatured ? (
                            <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                              Featured
                            </span>
                          ) : null}
                        </>
                      ) : (
                        <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-600">
                          Deleted
                        </span>
                      )}
                    </p>
                    <p className="text-xs text-zinc-500">
                      {product.slug}
                      {product.sku ? ` · ${product.sku}` : ""}
                      {product.brandName ? ` · ${product.brandName}` : ""}
                      {product.catalogProductTypeName
                        ? ` · ${product.catalogProductTypeName}`
                        : ""}
                      {product.storeName ? ` · ${product.storeName}` : ""}
                    </p>
                    {catalogView === "deleted" && product.deletedAt ? (
                      <p className="mt-1 text-xs text-zinc-500">
                        Deleted {new Date(product.deletedAt).toLocaleString()}
                      </p>
                    ) : null}
                    {catalogView === "active" ? (
                      <>
                        <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-600">
                          <span>{formatAdminPriceRange(product.priceRange)}</span>
                          <span>
                            {product.variantsCount} variant
                            {product.variantsCount === 1 ? "" : "s"}
                          </span>
                          <span>
                            {formatAdminStockSummary(product.stockSummary, product.variantsCount)}
                          </span>
                        </div>
                        {product.shortDescription ? (
                          <p className="mt-1 text-xs text-zinc-600">{product.shortDescription}</p>
                        ) : null}
                      </>
                    ) : (
                      <p className="mt-1 text-xs text-zinc-600">
                        {product.variantsCount} variant{product.variantsCount === 1 ? "" : "s"}
                      </p>
                    )}
                  </div>
                  <div className="flex shrink-0 flex-wrap gap-1">
                    {catalogView === "active" ? (
                      <>
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
                      </>
                    ) : (
                      <>
                        <button
                          type="button"
                          className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                          onClick={() => void handleRestore(product)}
                        >
                          Restore
                        </button>
                        {canForceDelete ? (
                          <button
                            type="button"
                            className="rounded px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50"
                            onClick={() => setForceDeleteTarget(product)}
                          >
                            Permanently delete
                          </button>
                        ) : null}
                      </>
                    )}
                  </div>
                </li>
                );
              })}
            </ul>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-4 py-3">
              <p className="text-xs text-zinc-500">
                Showing page {currentPage} of {lastPage} · {total}{" "}
                {catalogView === "deleted" ? "deleted" : ""} products
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
    </div>
  );
}
