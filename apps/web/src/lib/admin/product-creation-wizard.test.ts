import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  calculateProductCreationWizardProgress,
  isWizardStepComplete,
  nextWizardStepId,
  normalizeWizardStepId,
  previousWizardStepId,
  resolveProductCreationWizardSteps,
  shouldUseProductCreationWizard,
  validateWizardBasicStep,
  validateWizardStepBeforeContinue,
} from "@/lib/admin/product-creation-wizard";

describe("product creation wizard", () => {
  const chinaSteps = resolveProductCreationWizardSteps("china");
  const tzSteps = resolveProductCreationWizardSteps("tz");

  it("uses journey-specific step sequences", () => {
    assert.deepEqual(
      chinaSteps.map((step) => step.id),
      ["basic", "media", "pricing", "variants", "shipping", "china-import", "review"],
    );
    assert.deepEqual(
      tzSteps.map((step) => step.id),
      ["basic", "media", "pricing", "variants", "store", "review"],
    );
  });

  it("validates basic step requirements", () => {
    assert.match(
      validateWizardBasicStep({
        name: "",
        commerceJourney: "china",
        departmentId: "d1",
        categoryId: "c1",
        subcategoryId: "s1",
        catalogProductTypeId: "t1",
        brandId: "",
        description: "",
        price: 0,
        storeId: "",
        supplierId: "",
      }) ?? "",
      /name/i,
    );
  });

  it("navigates forward and backward across steps", () => {
    assert.equal(nextWizardStepId("basic", chinaSteps), "media");
    assert.equal(previousWizardStepId("media", chinaSteps), "basic");
    assert.equal(nextWizardStepId("review", chinaSteps), null);
  });

  it("normalizes invalid persisted step ids", () => {
    assert.equal(normalizeWizardStepId("unknown", chinaSteps), "basic");
    assert.equal(normalizeWizardStepId("shipping", chinaSteps), "shipping");
  });

  it("calculates draft progress and missing publish labels", () => {
    const progress = calculateProductCreationWizardProgress({
      steps: chinaSteps,
      form: {
        id: "prod-1",
        name: "Draft Blouse",
        commerceJourney: "china",
        departmentId: "d1",
        categoryId: "c1",
        subcategoryId: "s1",
        catalogProductTypeId: "t1",
        brandId: "b1",
        description: "Desc",
        price: 0,
        storeId: "",
        supplierId: "",
      },
      mediaCount: 0,
      hasPrimaryImage: false,
      variantCount: 0,
      sellableVariantCount: 0,
      hasPublishableShipping: false,
      publishReadiness: {
        ready: false,
        items: [],
        missing: [
          { id: "simple-price", label: "Base price greater than zero", met: false },
          { id: "china-supplier", label: "Supplier assigned", met: false },
        ],
        completed: [],
        path: "simple",
      },
    });

    assert.ok(progress.percent < 100);
    assert.ok(progress.missingPublishLabels.includes("Base price greater than zero"));
    assert.equal(isWizardStepComplete("basic", {
      form: {
        id: "prod-1",
        name: "Draft Blouse",
        commerceJourney: "china",
        departmentId: "d1",
        categoryId: "c1",
        subcategoryId: "s1",
        catalogProductTypeId: "t1",
        brandId: "",
        description: "",
        price: 0,
        storeId: "",
        supplierId: "",
      },
      mediaCount: 0,
      hasPrimaryImage: false,
      variantCount: 0,
      sellableVariantCount: 0,
      hasPublishableShipping: false,
      publishReadiness: null,
    }), true);
  });

  it("requires supplier on china-import step and store on tz store step", () => {
    assert.match(
      validateWizardStepBeforeContinue("china-import", {
        id: "p1",
        name: "Item",
        commerceJourney: "china",
        departmentId: "d1",
        categoryId: "c1",
        subcategoryId: "s1",
        catalogProductTypeId: "t1",
        brandId: "",
        description: "",
        price: 1000,
        storeId: "",
        supplierId: "",
      }) ?? "",
      /supplier/i,
    );

    assert.match(
      validateWizardStepBeforeContinue("store", {
        id: "p1",
        name: "Item",
        commerceJourney: "tz",
        departmentId: "d1",
        categoryId: "c1",
        subcategoryId: "s1",
        catalogProductTypeId: "t1",
        brandId: "",
        description: "",
        price: 1000,
        storeId: "",
        supplierId: "",
      }) ?? "",
      /store/i,
    );
  });

  it("uses wizard flow for new and draft products only", () => {
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: true, status: "draft" }), true);
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: false, status: "draft" }), true);
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: false, status: "active" }), false);
  });
});
