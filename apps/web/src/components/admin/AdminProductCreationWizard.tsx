"use client";

import { useEffect, useMemo, useState } from "react";
import { AdminBrandAsyncSelect } from "@/components/admin/AdminBrandAsyncSelect";
import { AdminCategoryTreeSelect } from "@/components/admin/AdminCategoryTreeSelect";
import { AdminProductWizardProgress } from "@/components/admin/AdminProductWizardProgress";
import { AdminSupplierAsyncSelect } from "@/components/admin/AdminSupplierAsyncSelect";
import { ProductMediaManager } from "@/components/admin/ProductMediaManager";
import { ProductShippingManager } from "@/components/admin/ProductShippingManager";
import { ProductStockManager } from "@/components/admin/ProductStockManager";
import { ProductVariantsManager } from "@/components/admin/ProductVariantsManager";
import { PublishReadinessChecklist } from "@/components/admin/PublishReadinessChecklist";
import { resolveBrandLeafCategoryId } from "@/lib/admin/catalog-selector-utils";
import {
  calculateProductCreationWizardProgress,
  mapWizardMissingSummary,
  nextWizardStepId,
  normalizeWizardStepId,
  persistWizardStep,
  previousWizardStepId,
  readPersistedWizardStep,
  resolveProductCreationWizardSteps,
  type ProductCreationWizardStepId,
  validateWizardStepBeforeContinue,
} from "@/lib/admin/product-creation-wizard";
import type { ProductPublishReadinessResult } from "@/lib/admin/product-publish-readiness";
import {
  fetchAdminProductMedia,
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
  shortDescription: string;
  description: string;
  commerceJourney: CommerceJourney | "";
  storeId: string;
  departmentId: string;
  categoryId: string;
  subcategoryId: string;
  catalogProductTypeId: string;
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
  onRefreshPublishContext: () => void;
  onRefreshShipping: () => void;
};

export function AdminProductCreationWizard({
  form,
  setForm,
  departments,
  categories,
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
}: AdminProductCreationWizardProps) {
  const steps = useMemo(
    () =>
      resolveProductCreationWizardSteps(form.commerceJourney).map((step) => step),
    [form.commerceJourney],
  );

  const [currentStepId, setCurrentStepId] = useState<ProductCreationWizardStepId>(() =>
    normalizeWizardStepId(initialStepId ?? readPersistedWizardStep(form.id ?? ""), steps),
  );
  const [mediaCount, setMediaCount] = useState(0);
  const [hasPrimaryImage, setHasPrimaryImage] = useState(false);
  const [stepError, setStepError] = useState<string | null>(null);

  const formTypes = useMemo(() => {
    const scopeId = form.subcategoryId || form.categoryId;
    if (!scopeId) {
      return [];
    }
    return productTypes.filter((type) => type.subcategoryId === scopeId);
  }, [form.categoryId, form.subcategoryId, productTypes]);

  useEffect(() => {
    setCurrentStepId((previous) => normalizeWizardStepId(previous, steps));
  }, [steps]);

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
                }));
              }}
            >
              <option value="">Select commerce context</option>
              <option value="china">CHINA_IMPORT — Order From China</option>
              <option value="tz">TZ_LOCAL — Buy From Tanzania</option>
            </select>
          </div>
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
          <div>
            <label className="admin-label" htmlFor="wizard-category">
              Category *
            </label>
            <AdminCategoryTreeSelect
              id="wizard-category"
              categories={categories}
              departmentId={form.departmentId}
              categoryId={form.categoryId}
              subcategoryId={form.subcategoryId}
              disabled={!form.departmentId}
              onChange={(selection) =>
                setForm((current) => ({
                  ...current,
                  categoryId: selection.categoryId,
                  subcategoryId: selection.subcategoryId,
                  catalogProductTypeId: "",
                }))
              }
            />
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
              onChange={(event) =>
                setForm((current) => ({ ...current, catalogProductTypeId: event.target.value }))
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

      {currentStepId === "pricing" ? (
        <div className="max-w-md space-y-3">
          <label className="admin-label" htmlFor="wizard-price">
            Selling price (TZS)
          </label>
          <input
            id="wizard-price"
            type="number"
            min={0}
            step={1}
            className="admin-input mt-1.5"
            value={form.price || ""}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                price: Number(event.target.value) || 0,
              }))
            }
          />
          <p className="text-xs text-zinc-500">
            Simple products use this base price. Variant products manage pricing on the Variants step.
          </p>
          {form.id && publishSellableVariantCount === 0 ? (
            <ProductStockManager
              productId={form.id}
              onStockSaved={() => {
                void onRefreshPublishContext();
              }}
            />
          ) : null}
        </div>
      ) : null}

      {currentStepId === "variants" && form.id ? (
        <ProductVariantsManager
          productId={form.id}
        />
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

      {currentStepId === "store" ? (
        <div className="max-w-lg space-y-3">
          <label className="admin-label" htmlFor="wizard-store">
            Store *
          </label>
          <select
            id="wizard-store"
            className="admin-input mt-1.5"
            value={form.storeId}
            disabled={storesLoading}
            onChange={(event) => setForm((current) => ({ ...current, storeId: event.target.value }))}
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
          {storesError ? <p className="text-xs text-red-600">{storesError}</p> : null}
          <p className="text-xs text-zinc-500">
            TZ_LOCAL products must belong to a store before publishing.
          </p>
        </div>
      ) : null}

      {currentStepId === "review" ? (
        <div className="space-y-4">
          {publishReadiness ? (
            <PublishReadinessChecklist readiness={publishReadiness} showWarning={!publishReadiness.ready} />
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
