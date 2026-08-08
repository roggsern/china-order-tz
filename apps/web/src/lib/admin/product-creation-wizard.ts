import type { ProductPublishReadinessResult } from "@/lib/admin/product-publish-readiness";
import type { CommerceJourney } from "@/lib/api/admin-catalog";

export type ProductCreationPricingModel = "simple" | "variants";

export type ApiProductPricingModel = "simple" | "variant";

export function mapWizardPricingModelToApi(
  pricingModel: ProductCreationPricingModel,
): ApiProductPricingModel {
  return pricingModel === "variants" ? "variant" : "simple";
}

export function mapApiPricingModelToWizard(
  pricingModel: string | null | undefined,
): ProductCreationPricingModel {
  return pricingModel === "variant" ? "variants" : "simple";
}

export function resolveWizardPricingModelFromProduct(
  pricingModel: string | null | undefined,
): ProductCreationPricingModel {
  return mapApiPricingModelToWizard(pricingModel);
}

export type ProductCreationWizardStepId =
  | "basic"
  | "media"
  | "pricing"
  | "variants"
  | "shipping"
  | "china-import"
  | "store"
  | "review";

export type ProductCreationWizardStep = {
  id: ProductCreationWizardStepId;
  label: string;
};

export type ProductCreationWizardFormSnapshot = {
  id?: string;
  name: string;
  commerceJourney: CommerceJourney | "";
  pricingModel: ProductCreationPricingModel;
  departmentId: string;
  categoryId: string;
  subcategoryId: string;
  catalogProductTypeId: string;
  brandId: string;
  description: string;
  price: number;
  storeId: string;
  supplierId: string;
};

export type ProductCreationWizardProgressInput = {
  steps: ProductCreationWizardStep[];
  form: ProductCreationWizardFormSnapshot;
  mediaCount: number;
  hasPrimaryImage: boolean;
  variantCount: number;
  sellableVariantCount: number;
  hasPublishableShipping: boolean;
  publishReadiness: ProductPublishReadinessResult | null;
};

export type ProductCreationWizardStepStatus = "not_started" | "incomplete" | "complete";

export type ProductCreationWizardProgress = {
  percent: number;
  completedStepIds: ProductCreationWizardStepId[];
  incompleteStepIds: ProductCreationWizardStepId[];
  missingPublishLabels: string[];
  readyForReview: boolean;
  readyToPublish: boolean;
};

/** Free section navigation is available only after the first draft save. */
export function canFreelyNavigateWizardSteps(productId: string | undefined): boolean {
  return Boolean(productId?.trim());
}

/**
 * Before draft: only Basic is selectable.
 * After draft: every step in the current journey list is selectable.
 */
export function canSelectWizardStep(
  stepId: ProductCreationWizardStepId,
  steps: ProductCreationWizardStep[],
  productId: string | undefined,
): boolean {
  if (!steps.some((step) => step.id === stepId)) {
    return false;
  }
  if (!canFreelyNavigateWizardSteps(productId)) {
    return stepId === "basic";
  }
  return true;
}

function wizardStepHasPartialProgress(
  stepId: ProductCreationWizardStepId,
  input: Omit<ProductCreationWizardProgressInput, "steps">,
): boolean {
  switch (stepId) {
    case "basic":
      return (
        input.form.name.trim().length > 0 ||
        input.form.commerceJourney === "china" ||
        input.form.commerceJourney === "tz" ||
        input.form.departmentId.trim().length > 0 ||
        input.form.storeId.trim().length > 0 ||
        input.form.categoryId.trim().length > 0 ||
        input.form.subcategoryId.trim().length > 0 ||
        input.form.catalogProductTypeId.trim().length > 0
      );
    case "media":
      return input.mediaCount > 0;
    case "pricing":
      return input.form.price > 0 || input.sellableVariantCount > 0;
    case "variants":
      return input.variantCount > 0;
    case "shipping":
      return input.hasPublishableShipping;
    case "china-import":
      return input.form.supplierId.trim().length > 0;
    case "store":
      return input.form.storeId.trim().length > 0;
    case "review":
      return (input.publishReadiness?.items.length ?? 0) > 0;
    default:
      return false;
  }
}

export function resolveWizardStepStatus(
  stepId: ProductCreationWizardStepId,
  input: Omit<ProductCreationWizardProgressInput, "steps">,
): ProductCreationWizardStepStatus {
  if (isWizardStepComplete(stepId, input)) {
    return "complete";
  }
  if (wizardStepHasPartialProgress(stepId, input)) {
    return "incomplete";
  }
  return "not_started";
}

export function wizardStepStatusLabel(status: ProductCreationWizardStepStatus): string {
  switch (status) {
    case "complete":
      return "Complete";
    case "incomplete":
      return "Incomplete";
    default:
      return "Not Started";
  }
}

export function wizardStepStatusGlyph(status: ProductCreationWizardStepStatus): string {
  switch (status) {
    case "complete":
      return "✔";
    case "incomplete":
      return "⚠";
    default:
      return "○";
  }
}

const WIZARD_STEP_STORAGE_PREFIX = "admin-product-wizard-step:";

export function resolveProductCreationWizardSteps(
  commerceJourney: CommerceJourney | "",
  pricingModel: ProductCreationPricingModel = "simple",
): ProductCreationWizardStep[] {
  if (commerceJourney !== "china" && commerceJourney !== "tz") {
    return [{ id: "basic", label: "Basic information" }];
  }

  const basic: ProductCreationWizardStep[] = [
    { id: "basic", label: "Basic information" },
    { id: "media", label: "Media" },
  ];

  if (pricingModel === "variants") {
    if (commerceJourney === "china") {
      return [
        ...basic,
        { id: "variants", label: "Variants" },
        { id: "shipping", label: "Shipping" },
        { id: "china-import", label: "China import" },
        { id: "review", label: "Review & publish" },
      ];
    }

    return [
      ...basic,
      { id: "variants", label: "Variants" },
      { id: "review", label: "Review & publish" },
    ];
  }

  const simpleCore: ProductCreationWizardStep[] = [
    ...basic,
    { id: "pricing", label: "Pricing" },
  ];

  if (commerceJourney === "china") {
    return [
      ...simpleCore,
      { id: "shipping", label: "Shipping" },
      { id: "china-import", label: "China import" },
      { id: "review", label: "Review & publish" },
    ];
  }

  return [...simpleCore, { id: "review", label: "Review & publish" }];
}

export function inferProductCreationPricingModel(input: {
  persistedPricingModel?: string | null;
  variantCount: number;
  sellableVariantCount: number;
  price: number;
  hasSimpleInventoryPolicy: boolean;
}): ProductCreationPricingModel {
  if (input.persistedPricingModel === "simple" || input.persistedPricingModel === "variant") {
    return mapApiPricingModelToWizard(input.persistedPricingModel);
  }

  if (input.sellableVariantCount > 0 || input.variantCount > 0) {
    return "variants";
  }

  if (input.price > 0 || input.hasSimpleInventoryPolicy) {
    return "simple";
  }

  return "simple";
}

export function wizardSavePricingFields(
  pricingModel: ProductCreationPricingModel,
  price: number,
  costPrice: number | null,
): { price: number; cost_price: number | null } {
  if (pricingModel === "variants") {
    return { price: 0, cost_price: null };
  }

  return { price, cost_price: costPrice };
}

export function isWizardStepComplete(
  stepId: ProductCreationWizardStepId,
  input: Omit<ProductCreationWizardProgressInput, "steps">,
): boolean {
  switch (stepId) {
    case "basic":
      // Sprint 1: Basic is complete once the draft product record exists.
      return Boolean(input.form.id?.trim());
    case "media":
      return input.form.id !== undefined && input.mediaCount > 0 && input.hasPrimaryImage;
    case "pricing":
      if (input.sellableVariantCount > 0) {
        return true;
      }
      return input.form.price > 0;
    case "variants":
      // Variant path: complete after generation produced at least one variant.
      // Simple path omits this step from the journey list.
      return input.variantCount > 0;
    case "shipping":
      return input.hasPublishableShipping;
    case "china-import":
      return input.form.supplierId.trim().length > 0;
    case "store":
      return input.form.storeId.trim().length > 0;
    case "review":
      return input.publishReadiness?.ready === true;
    default:
      return false;
  }
}

export function calculateProductCreationWizardProgress(
  input: ProductCreationWizardProgressInput,
): ProductCreationWizardProgress {
  const progressInput = {
    form: input.form,
    mediaCount: input.mediaCount,
    hasPrimaryImage: input.hasPrimaryImage,
    variantCount: input.variantCount,
    sellableVariantCount: input.sellableVariantCount,
    hasPublishableShipping: input.hasPublishableShipping,
    publishReadiness: input.publishReadiness,
  };

  const completedStepIds = input.steps
    .filter((step) => isWizardStepComplete(step.id, progressInput))
    .map((step) => step.id);
  const incompleteStepIds = input.steps
    .filter((step) => !isWizardStepComplete(step.id, progressInput))
    .map((step) => step.id);

  const actionableSteps = input.steps.filter((step) => step.id !== "review");
  const completedActionable = actionableSteps.filter((step) =>
    isWizardStepComplete(step.id, progressInput),
  ).length;
  const percent =
    actionableSteps.length === 0
      ? 0
      : Math.round((completedActionable / actionableSteps.length) * 100);

  const missingPublishLabels = input.publishReadiness?.missing.map((item) => item.label) ?? [];
  const readyToPublish = input.publishReadiness?.ready === true;
  const readyForReview =
    readyToPublish ||
    (percent >= 70 && missingPublishLabels.length <= 2);

  return {
    percent,
    completedStepIds,
    incompleteStepIds,
    missingPublishLabels,
    readyForReview,
    readyToPublish,
  };
}

export function validateWizardBasicStep(form: ProductCreationWizardFormSnapshot): string | null {
  if (!form.name.trim()) {
    return "Product name is required.";
  }
  if (!form.id && form.commerceJourney !== "china" && form.commerceJourney !== "tz") {
    return "Select a commerce context.";
  }
  if (form.commerceJourney === "tz" && !form.storeId.trim()) {
    return "Store is required.";
  }
  if (form.commerceJourney === "china" && !form.departmentId.trim()) {
    return "Department is required.";
  }
  if (!form.subcategoryId.trim() && !form.categoryId.trim()) {
    return "Category is required.";
  }
  if (!form.catalogProductTypeId.trim()) {
    return "Product type is required.";
  }
  if (
    form.pricingModel !== "simple" &&
    form.pricingModel !== "variants"
  ) {
    return "Select whether this product uses simple pricing or variants.";
  }
  return null;
}

export function validateWizardStepBeforeContinue(
  stepId: ProductCreationWizardStepId,
  form: ProductCreationWizardFormSnapshot,
): string | null {
  if (stepId === "basic") {
    return validateWizardBasicStep(form);
  }
  if (stepId === "china-import" && form.commerceJourney === "china" && !form.supplierId.trim()) {
    return "Select a supplier for China import products.";
  }
  return null;
}

export function normalizeWizardStepId(
  stepId: string | null | undefined,
  steps: ProductCreationWizardStep[],
): ProductCreationWizardStepId {
  const allowed = new Set(steps.map((step) => step.id));
  if (stepId && allowed.has(stepId as ProductCreationWizardStepId)) {
    return stepId as ProductCreationWizardStepId;
  }
  return steps[0]?.id ?? "basic";
}

export function nextWizardStepId(
  current: ProductCreationWizardStepId,
  steps: ProductCreationWizardStep[],
): ProductCreationWizardStepId | null {
  const index = steps.findIndex((step) => step.id === current);
  if (index < 0 || index >= steps.length - 1) {
    return null;
  }
  return steps[index + 1]?.id ?? null;
}

export function previousWizardStepId(
  current: ProductCreationWizardStepId,
  steps: ProductCreationWizardStep[],
): ProductCreationWizardStepId | null {
  const index = steps.findIndex((step) => step.id === current);
  if (index <= 0) {
    return null;
  }
  return steps[index - 1]?.id ?? null;
}

export function persistWizardStep(productId: string, stepId: ProductCreationWizardStepId): void {
  if (typeof window === "undefined" || !productId.trim()) {
    return;
  }
  try {
    window.sessionStorage.setItem(`${WIZARD_STEP_STORAGE_PREFIX}${productId}`, stepId);
  } catch {
    // Ignore storage failures.
  }
}

export function readPersistedWizardStep(productId: string): ProductCreationWizardStepId | null {
  if (typeof window === "undefined" || !productId.trim()) {
    return null;
  }
  try {
    const value = window.sessionStorage.getItem(`${WIZARD_STEP_STORAGE_PREFIX}${productId}`);
    return value as ProductCreationWizardStepId | null;
  } catch {
    return null;
  }
}

export function shouldUseProductCreationWizard(input: {
  isNewProduct: boolean;
  status: "draft" | "active" | "archived";
}): boolean {
  return input.isNewProduct || input.status === "draft";
}

/**
 * Specs are product-level catalog attributes. Editing requires a persisted product
 * (draft or published); schema comes from the product's catalog product type.
 */
export function canAccessProductSpecificationsWorkspace(
  productId: string | null | undefined,
): boolean {
  return typeof productId === "string" && productId.trim() !== "";
}

export type ProductEditorSurface = "specifications" | "wizard" | "standard";

/**
 * Draft products stay in the creation wizard, but Specs can open as a workspace
 * overlay when a product id exists — without adding a wizard step.
 */
export function resolveProductEditorSurface(input: {
  productId: string | null | undefined;
  useWizardFlow: boolean;
  formTab: string;
}): ProductEditorSurface {
  if (canAccessProductSpecificationsWorkspace(input.productId) && input.formTab === "specifications") {
    return "specifications";
  }

  if (input.useWizardFlow) {
    return "wizard";
  }

  return "standard";
}

export function mapWizardMissingSummary(progress: ProductCreationWizardProgress): string[] {
  if (progress.missingPublishLabels.length > 0) {
    return progress.missingPublishLabels;
  }

  return progress.incompleteStepIds
    .filter((stepId) => stepId !== "review")
    .map((stepId) => {
      switch (stepId) {
        case "basic":
          return "Basic information";
        case "media":
          return "Images";
        case "pricing":
          return "Price";
        case "variants":
          return "Variants";
        case "shipping":
          return "Shipping";
        case "china-import":
          return "Supplier";
        case "store":
          return "Store";
        default:
          return stepId;
      }
    });
}
