"use client";

import { useEffect, useMemo, useState } from "react";
import { AdminBrandAsyncSelect } from "@/components/admin/AdminBrandAsyncSelect";
import { AdminCategoryTreeSelect } from "@/components/admin/AdminCategoryTreeSelect";
import { AdminProductWizardProgress } from "@/components/admin/AdminProductWizardProgress";
import { AdminSupplierAsyncSelect } from "@/components/admin/AdminSupplierAsyncSelect";
import { ProductMediaManager } from "@/components/admin/ProductMediaManager";
import { ProductShippingManager } from "@/components/admin/ProductShippingManager";
import { ProductCommercialAvailabilityManager } from "@/components/admin/ProductCommercialAvailabilityManager";
import { ProductSimplePricingFields } from "@/components/admin/ProductSimplePricingFields";
import { ProductStockManager } from "@/components/admin/ProductStockManager";
import { ProductVariantsManager } from "@/components/admin/ProductVariantsManager";
import { PublishReadinessChecklist } from "@/components/admin/PublishReadinessChecklist";
import { resolveBrandLeafCategoryId } from "@/lib/admin/catalog-selector-utils";
import { filterCatalogProductTypesForCategoryScope } from "@/lib/admin/catalog-product-type-scope";
import {
  calculateProductCreationWizardProgress,
  canSelectWizardStep,
  mapWizardMissingSummary,
  nextWizardStepId,
  normalizeWizardStepId,
  persistWizardStep,
  previousWizardStepId,
  readPersistedWizardStep,
  resolveProductCreationWizardSteps,
  resolveWizardStepStatus,
  type ProductCreationPricingModel,
  type ProductCreationWizardStepId,
  type ProductCreationWizardStepStatus,
  validateWizardStepBeforeContinue,
} from "@/lib/admin/product-creation-wizard";
import type { ProductPublishReadinessResult } from "@/lib/admin/product-publish-readiness";
import {
  fetchAdminProductMedia,
  fetchAdminCategories,
  type AdminBrand,
  type AdminCatalogProductType,
  type AdminCategory,
  type AdminDepartment,
  type AdminStoreOption,
  type CommerceJourney,
} from "@/lib/api/admin-catalog";
import type { AdminSupplier } from "@/lib/api/admin-procurement";

export type ProductCreationWizardFormState = {
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

type AdminProductCreationWizardProps = {
  form: ProductCreationWizardFormState;
  setForm: (updater: (current: ProductCreationWizardFormState) => ProductCreationWizardFormState) => void;
  departments: AdminDepartment[];
  categories: AdminCategory[];
  productTypes: AdminCatalogProductType[];
  brands: AdminBrand[];
  suppliers: AdminSupplier[];
  stores: AdminStoreOption[];
  storesLoading: boolean;
  storesError: string | null;
  canCreateBrand: boolean;
  saving: boolean;
  actionError: string | null;
  publishReadiness: ProductPublishReadinessResult | null;
  publishVariantCount: number;
  publishSellableVariantCount: number;
  hasPublishableShipping: boolean;
  initialStepId?: ProductCreationWizardStepId;
  onSaveDraft: (options?: { strictStepValidation?: boolean }) => Promise<boolean>;
  onPublish: () => Promise<void>;
  onCancel: () => void;
  onRefreshPublishContext: () => void | Promise<void>;
  onRefreshShipping: () => void;
  publishContextRefreshing?: boolean;
  publishRefreshError?: string | null;
};

export function AdminProductCreationWizard({
  form,
  setForm,
  departments,
  categories: _categories,
  productTypes,
  brands,
  suppliers,
  stores,
  storesLoading,
  storesError,
  canCreateBrand,
  saving,
  actionError,
  publishReadiness,
  publishVariantCount,
  publishSellableVariantCount,
  hasPublishableShipping,
  initialStepId,
  onSaveDraft,
  onPublish,
  onCancel,
  onRefreshPublishContext,
  onRefreshShipping,
  publishContextRefreshing = false,
  publishRefreshError = null,
}: AdminProductCreationWizardProps) {
  const steps = useMemo(
    () => resolveProductCreationWizardSteps(form.commerceJourney, form.pricingModel),
    [form.commerceJourney, form.pricingModel],
  );

  const [currentStepId, setCurrentStepId] = useState<ProductCreationWizardStepId>(() =>
    normalizeWizardStepId(initialStepId ?? readPersistedWizardStep(form.id ?? ""), steps),
  );
  const [mediaCount, setMediaCount] = useState(0);
  const [hasPrimaryImage, setHasPrimaryImage] = useState(false);
  const [stepError, setStepError] = useState<string | null>(null);
  const [taxonomyCategories, setTaxonomyCategories] = useState<AdminCategory[]>([]);
  const [taxonomyLoading, setTaxonomyLoading] = useState(false);
  const [taxonomyError, setTaxonomyError] = useState<string | null>(null);

  const isTzLocal = form.commerceJourney === "tz";
  const isChinaImport = form.commerceJourney === "china";

  const formTypes = useMemo(() => {
    return filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: form.categoryId,
      subcategoryId: form.subcategoryId,
      categories: taxonomyCategories,
    });
  }, [form.categoryId, form.subcategoryId, productTypes, taxonomyCategories]);

  useEffect(() => {
    setCurrentStepId((previous) => normalizeWizardStepId(previous, steps));
  }, [steps]);

  useEffect(() => {
    if (form.commerceJourney === "china") {
      if (!form.departmentId) {
        setTaxonomyCategories([]);
        setTaxonomyError(null);
        return;
      }

      let cancelled = false;
      setTaxonomyLoading(true);
      setTaxonomyError(null);

      void fetchAdminCategories({
        origin: "china",
        departmentId: form.departmentId,
      })
        .then((items) => {
          if (!cancelled) {
            setTaxonomyCategories(items);
          }
        })
        .catch(() => {
          if (!cancelled) {
            setTaxonomyCategories([]);
            setTaxonomyError("Unable to load China catalog categories.");
          }
        })
        .finally(() => {
          if (!cancelled) {
            setTaxonomyLoading(false);
          }
        });

      return () => {
        cancelled = true;
      };
    }

    if (form.commerceJourney === "tz") {
      if (!form.storeId) {
        setTaxonomyCategories([]);
        setTaxonomyError(null);
        return;
      }

      let cancelled = false;
      setTaxonomyLoading(true);
      setTaxonomyError(null);

      void fetchAdminCategories({
        origin: "tz",
        storeId: form.storeId,
        isActive: true,
      })
        .then((items) => {
          if (!cancelled) {
            setTaxonomyCategories(items);
          }
        })
        .catch(() => {
          if (!cancelled) {
            setTaxonomyCategories([]);
            setTaxonomyError("Unable to load store categories.");
          }
        })
        .finally(() => {
          if (!cancelled) {
            setTaxonomyLoading(false);
          }
        });

      return () => {
        cancelled = true;
      };
    }

    setTaxonomyCategories([]);
    setTaxonomyError(null);
    return undefined;
  }, [form.commerceJourney, form.departmentId, form.storeId]);

  useEffect(() => {
    if (!form.id) {
      setMediaCount(0);
      setHasPrimaryImage(false);
      return;
    }

    let cancelled = false;

    void (async () => {
      try {
        const media = await fetchAdminProductMedia(form.id!);
        if (cancelled) {
          return;
        }
        setMediaCount(media.length);
        setHasPrimaryImage(media.some((item) => item.isPrimary));
      } catch {
        if (!cancelled) {
          setMediaCount(0);
          setHasPrimaryImage(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [form.id, currentStepId]);

  useEffect(() => {
    if (form.id) {
      persistWizardStep(form.id, currentStepId);
    }
  }, [form.id, currentStepId]);

  const progress = useMemo(
    () =>
      calculateProductCreationWizardProgress({
        steps,
        form,
        mediaCount,
        hasPrimaryImage,
        variantCount: publishVariantCount,
        sellableVariantCount: publishSellableVariantCount,
        hasPublishableShipping,
        publishReadiness,
      }),
    [
      steps,
      form,
      mediaCount,
      hasPrimaryImage,
      publishVariantCount,
      publishSellableVariantCount,
      hasPublishableShipping,
      publishReadiness,
    ],
  );

  const missingSummary = mapWizardMissingSummary(progress);

  const stepStatuses = useMemo(() => {
    const progressInput = {
      form,
      mediaCount,
      hasPrimaryImage,
      variantCount: publishVariantCount,
      sellableVariantCount: publishSellableVariantCount,
      hasPublishableShipping,
      publishReadiness,
    };
    return Object.fromEntries(
      steps.map((step) => [step.id, resolveWizardStepStatus(step.id, progressInput)]),
    ) as Record<ProductCreationWizardStepId, ProductCreationWizardStepStatus>;
  }, [
    steps,
    form,
    mediaCount,
    hasPrimaryImage,
    publishVariantCount,
    publishSellableVariantCount,
    hasPublishableShipping,
    publishReadiness,
  ]);

  const handleSelectStep = (stepId: ProductCreationWizardStepId) => {
    if (!canSelectWizardStep(stepId, steps, form.id)) {
      return;
    }
    setStepError(null);
    setCurrentStepId(stepId);
  };

  const handleSaveDraft = async () => {
    setStepError(null);
    const saved = await onSaveDraft({ strictStepValidation: false });
    if (saved && form.id) {
      persistWizardStep(form.id, currentStepId);
    }
  };

  const handleContinue = async () => {
    const validationError = validateWizardStepBeforeContinue(currentStepId, form);
    if (validationError) {
      setStepError(validationError);
      return;
    }

    setStepError(null);
    const saved = await onSaveDraft({ strictStepValidation: currentStepId === "basic" });
    if (!saved) {
      return;
    }

    const next = nextWizardStepId(currentStepId, steps);
    if (next) {
      setCurrentStepId(next);
    }
  };

  const handleBack = () => {
    setStepError(null);
    const previous = previousWizardStepId(currentStepId, steps);
    if (previous) {
      setCurrentStepId(previous);
    }
  };

  return (
    <div className="space-y-4">
      <AdminProductWizardProgress
        steps={steps}
        currentStepId={currentStepId}
        percent={progress.percent}
        missingSummary={missingSummary}
        stepStatuses={stepStatuses}
        canSelectStep={(stepId) => canSelectWizardStep(stepId, steps, form.id)}
        onSelectStep={handleSelectStep}
      />

      {actionError ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {actionError}
        </div>
      ) : null}
      {stepError ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {stepError}
        </div>
      ) : null}

      {currentStepId === "basic" ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className="admin-label" htmlFor="wizard-commerce-journey">
              Commerce context *
            </label>
            <select
              id="wizard-commerce-journey"
              className="admin-input mt-1.5"
              value={form.commerceJourney}
              disabled={Boolean(form.id)}
              onChange={(event) => {
                const commerceJourney =
                  event.target.value === "tz"
                    ? "tz"
                    : event.target.value === "china"
                      ? "china"
                      : "";
                setForm((current) => ({
                  ...current,
                  commerceJourney,
                  storeId: commerceJourney === "tz" ? current.storeId : "",
                  supplierId: commerceJourney === "china" ? current.supplierId : "",
                  departmentId: "",
                  categoryId: "",
                  subcategoryId: "",
                  catalogProductTypeId: "",
                }));
              }}
            >
              <option value="">Select commerce context</option>
              <option value="china">CHINA_IMPORT — Order From China</option>
              <option value="tz">TZ_LOCAL — Buy From Tanzania</option>
            </select>
          </div>
          {form.commerceJourney ? (
            <div className="sm:col-span-2">
              <fieldset>
                <legend className="admin-label">Pricing model *</legend>
                <div className="mt-2 grid gap-3 sm:grid-cols-2">
                  <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-3">
                    <input
                      type="radio"
                      name="wizard-pricing-model"
                      className="mt-1"
                      checked={form.pricingModel === "simple"}
                      disabled={Boolean(form.id)}
                      onChange={() =>
                        setForm((current) => ({ ...current, pricingModel: "simple" }))
                      }
                    />
                    <span>
                      <span className="block text-sm font-medium text-zinc-900">Simple product</span>
                      <span className="mt-0.5 block text-xs text-zinc-500">
                        One base price and product-level stock or commercial availability.
                      </span>
                    </span>
                  </label>
                  <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-3">
                    <input
                      type="radio"
                      name="wizard-pricing-model"
                      className="mt-1"
                      checked={form.pricingModel === "variants"}
                      disabled={Boolean(form.id)}
                      onChange={() =>
                        setForm((current) => ({ ...current, pricingModel: "variants" }))
                      }
                    />
                    <span>
                      <span className="block text-sm font-medium text-zinc-900">
                        Product with variants
                      </span>
                      <span className="mt-0.5 block text-xs text-zinc-500">
                        Skip simple pricing — set price and stock per variant instead.
                      </span>
                    </span>
                  </label>
                </div>
                {form.id ? (
                  <p className="mt-2 text-xs text-zinc-500">
                    Pricing model is locked after the draft product is created.
                  </p>
                ) : null}
              </fieldset>
            </div>
          ) : null}
          <div className="sm:col-span-2">
            <label className="admin-label" htmlFor="wizard-name">
              Product name *
            </label>
            <input
              id="wizard-name"
              className="admin-input mt-1.5"
              value={form.name}
              onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
            />
          </div>
          {isChinaImport ? (
            <div>
              <label className="admin-label" htmlFor="wizard-department">
                Department *
              </label>
              <select
                id="wizard-department"
                className="admin-input mt-1.5"
                value={form.departmentId}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    departmentId: event.target.value,
                    categoryId: "",
                    subcategoryId: "",
                    catalogProductTypeId: "",
                  }))
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
          ) : null}
          {isTzLocal ? (
            <div>
              <label className="admin-label" htmlFor="wizard-store-basic">
                Store *
              </label>
              <select
                id="wizard-store-basic"
                className="admin-input mt-1.5"
                value={form.storeId}
                disabled={storesLoading}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    storeId: event.target.value,
                    categoryId: "",
                    subcategoryId: "",
                    catalogProductTypeId: "",
                  }))
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
              {storesError ? <p className="mt-1 text-xs text-red-600">{storesError}</p> : null}
            </div>
          ) : null}
          <div>
            <label className="admin-label" htmlFor="wizard-category">
              Category *
            </label>
            <AdminCategoryTreeSelect
              id="wizard-category"
              categories={taxonomyCategories}
              departmentId={isChinaImport ? form.departmentId : undefined}
              storeId={isTzLocal ? form.storeId : undefined}
              categoryId={form.categoryId}
              subcategoryId={form.subcategoryId}
              disabled={
                taxonomyLoading ||
                (isChinaImport && !form.departmentId) ||
                (isTzLocal && !form.storeId)
              }
              onChange={(selection) =>
                setForm((current) => ({
                  ...current,
                  categoryId: selection.categoryId,
                  subcategoryId: selection.subcategoryId,
                  catalogProductTypeId: "",
                  productCondition: "",
                }))
              }
            />
            {taxonomyLoading ? (
              <p className="mt-1 text-xs text-zinc-500">Loading categories…</p>
            ) : null}
            {taxonomyError ? (
              <p className="mt-1 text-xs text-red-600">{taxonomyError}</p>
            ) : null}
          </div>
          <div>
            <label className="admin-label" htmlFor="wizard-type">
              Product type *
            </label>
            <select
              id="wizard-type"
              className="admin-input mt-1.5"
              value={form.catalogProductTypeId}
              disabled={!form.subcategoryId && !form.categoryId}
              onChange={(event) => {
                const nextTypeId = event.target.value;
                const nextType = formTypes.find((type) => type.id === nextTypeId);
                setForm((current) => ({
                  ...current,
                  catalogProductTypeId: nextTypeId,
                  productCondition: nextType?.supportsProductCondition
                    ? current.productCondition || "BRAND_NEW"
                    : "",
                }));
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
                      name="wizard-product-condition"
                      value={option.value}
                      checked={(form.productCondition || "BRAND_NEW") === option.value}
                      onChange={() =>
                        setForm((current) => ({
                          ...current,
                          productCondition: option.value,
                        }))
                      }
                    />
                    {option.label}
                  </label>
                ))}
              </div>
            </div>
          ) : null}
          <div>
            <label className="admin-label" htmlFor="wizard-brand">
              Brand
            </label>
            <AdminBrandAsyncSelect
              id="wizard-brand"
              value={form.brandId}
              selectedLabel={brands.find((brand) => brand.id === form.brandId)?.name}
              categoryId={resolveBrandLeafCategoryId(form.categoryId, form.subcategoryId)}
              canCreate={canCreateBrand}
              onChange={(brandId) => setForm((current) => ({ ...current, brandId }))}
            />
          </div>
          <div className="sm:col-span-2">
            <label className="admin-label" htmlFor="wizard-description">
              Description
            </label>
            <textarea
              id="wizard-description"
              className="admin-input mt-1.5 min-h-[96px]"
              value={form.description}
              onChange={(event) =>
                setForm((current) => ({ ...current, description: event.target.value }))
              }
            />
          </div>
        </div>
      ) : null}

      {currentStepId === "media" && form.id ? (
        <ProductMediaManager
          productId={form.id}
          productName={form.name || "Product"}
        />
      ) : null}

      {currentStepId === "pricing" && form.pricingModel === "simple" ? (
        <div className="max-w-2xl space-y-4">
          <ProductSimplePricingFields
            sellingPrice={form.price}
            costPrice={form.costPrice}
            sellingPriceId="wizard-price"
            costPriceId="wizard-cost-price"
            onSellingPriceChange={(price) =>
              setForm((current) => ({
                ...current,
                price,
              }))
            }
            onCostPriceChange={(costPrice) =>
              setForm((current) => ({
                ...current,
                costPrice,
              }))
            }
          />
          {form.commerceJourney === "china" && publishSellableVariantCount === 0 ? (
            <p className="text-xs text-sky-800">
              No variants. Commercial availability is managed at product level.
            </p>
          ) : null}
          {form.commerceJourney !== "china" && form.id && publishSellableVariantCount === 0 ? (
            <ProductStockManager
              productId={form.id}
              onStockSaved={() => {
                void onRefreshPublishContext();
              }}
            />
          ) : null}
          {form.commerceJourney === "china" && form.id && publishSellableVariantCount === 0 ? (
            <ProductCommercialAvailabilityManager
              productId={form.id}
              onSaved={() => {
                void onRefreshPublishContext();
              }}
            />
          ) : null}
        </div>
      ) : null}

      {currentStepId === "variants" && form.id ? (
        <div className="space-y-4">
          {form.commerceJourney === "china" ? (
            <p className="text-xs text-sky-800">
              Stock is managed per variant. Set commercial availability on the Commercial Availability
              tab after creating variants, or below once variants exist.
            </p>
          ) : null}
          <ProductVariantsManager
            productId={form.id}
            commerceChannelCode={form.commerceJourney === "china" ? "CHINA_IMPORT" : "TZ_LOCAL"}
            onVariantsChanged={onRefreshPublishContext}
          />
          {form.commerceJourney === "china" ? (
            <ProductCommercialAvailabilityManager
              productId={form.id}
              onSaved={() => {
                void onRefreshPublishContext();
              }}
            />
          ) : null}
        </div>
      ) : null}

      {currentStepId === "shipping" && form.id ? (
        <ProductShippingManager
          productId={form.id}
          onSaved={() => {
            void onRefreshShipping();
          }}
        />
      ) : null}

      {currentStepId === "china-import" ? (
        <div className="max-w-lg space-y-3">
          <label className="admin-label" htmlFor="wizard-supplier">
            Supplier *
          </label>
          <AdminSupplierAsyncSelect
            id="wizard-supplier"
            value={form.supplierId}
            selectedLabel={suppliers.find((supplier) => supplier.id === form.supplierId)?.name}
            onChange={(supplierId) => setForm((current) => ({ ...current, supplierId }))}
          />
          <p className="text-xs text-zinc-500">
            Required before publishing China import products. Used for supplier purchase orders.
          </p>
        </div>
      ) : null}

      {currentStepId === "review" ? (
        <div className="space-y-4">
          {publishReadiness ? (
            <PublishReadinessChecklist
              readiness={publishReadiness}
              showWarning={!publishReadiness.ready}
              refreshing={publishContextRefreshing}
              refreshError={publishRefreshError}
            />
          ) : null}
          {progress.readyForReview && !progress.readyToPublish ? (
            <p className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
              Ready for review — complete the remaining items below, then publish.
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2 border-t border-zinc-200 pt-4">
        <button
          type="button"
          className="admin-btn-secondary"
          disabled={saving || !previousWizardStepId(currentStepId, steps)}
          onClick={handleBack}
        >
          Back
        </button>
        <button
          type="button"
          className="admin-btn-secondary"
          disabled={saving}
          onClick={() => void handleSaveDraft()}
        >
          {saving ? "Saving…" : "Save draft"}
        </button>
        {currentStepId !== "review" ? (
          <button
            type="button"
            className="admin-btn-primary"
            disabled={saving}
            onClick={() => void handleContinue()}
          >
            {saving ? "Saving…" : form.id ? "Save & continue" : "Save draft & continue"}
          </button>
        ) : (
          <button
            type="button"
            className="admin-btn-primary"
            disabled={saving || !progress.readyToPublish}
            onClick={() => void onPublish()}
          >
            {saving ? "Publishing…" : "Publish product"}
          </button>
        )}
        <button type="button" className="admin-btn-secondary" disabled={saving} onClick={onCancel}>
          Exit wizard
        </button>
      </div>
    </div>
  );
}
