import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  calculateProductCreationWizardProgress,
  inferProductCreationPricingModel,
  isWizardStepComplete,
  mapApiPricingModelToWizard,
  mapWizardPricingModelToApi,
  nextWizardStepId,
  normalizeWizardStepId,
  previousWizardStepId,
  resolveProductCreationWizardSteps,
  resolveWizardPricingModelFromProduct,
  shouldUseProductCreationWizard,
  validateWizardBasicStep,
  validateWizardStepBeforeContinue,
  wizardSavePricingFields,
} from "@/lib/admin/product-creation-wizard";

const basicFormFields = {
  name: "Draft Product",
  departmentId: "d1",
  categoryId: "c1",
  subcategoryId: "s1",
  catalogProductTypeId: "t1",
  brandId: "",
  description: "",
  price: 0,
  storeId: "",
  supplierId: "",
};

describe("product creation wizard", () => {
  const chinaSimpleSteps = resolveProductCreationWizardSteps("china", "simple");
  const tzSimpleSteps = resolveProductCreationWizardSteps("tz", "simple");
  const chinaVariantSteps = resolveProductCreationWizardSteps("china", "variants");
  const tzVariantSteps = resolveProductCreationWizardSteps("tz", "variants");

  it("uses journey-specific step sequences for simple products", () => {
    assert.deepEqual(
      chinaSimpleSteps.map((step) => step.id),
      ["basic", "media", "pricing", "shipping", "china-import", "review"],
    );
    assert.deepEqual(
      tzSimpleSteps.map((step) => step.id),
      ["basic", "media", "pricing", "review"],
    );
  });

  it("skips pricing for variant-intent products", () => {
    assert.deepEqual(
      chinaVariantSteps.map((step) => step.id),
      ["basic", "media", "variants", "shipping", "china-import", "review"],
    );
    assert.deepEqual(
      tzVariantSteps.map((step) => step.id),
      ["basic", "media", "variants", "review"],
    );
    assert.equal(chinaVariantSteps.some((step) => step.id === "pricing"), false);
    assert.equal(tzVariantSteps.some((step) => step.id === "pricing"), false);
  });

  it("validates basic step requirements including pricing model", () => {
    assert.match(
      validateWizardBasicStep({
        ...basicFormFields,
        name: "",
        commerceJourney: "china",
        pricingModel: "simple",
      }) ?? "",
      /name/i,
    );

    assert.match(
      validateWizardBasicStep({
        ...basicFormFields,
        commerceJourney: "china",
        pricingModel: "" as "simple",
      }) ?? "",
      /simple pricing or variants/i,
    );
  });

  it("requires store on tz basic step and department on china basic step", () => {
    assert.match(
      validateWizardBasicStep({
        ...basicFormFields,
        name: "TZ Product",
        commerceJourney: "tz",
        pricingModel: "variants",
        departmentId: "",
        storeId: "",
      }) ?? "",
      /store/i,
    );

    assert.match(
      validateWizardBasicStep({
        ...basicFormFields,
        name: "China Product",
        commerceJourney: "china",
        pricingModel: "variants",
        departmentId: "",
      }) ?? "",
      /department/i,
    );
  });

  it("navigates forward and backward across simple and variant flows", () => {
    assert.equal(nextWizardStepId("basic", chinaSimpleSteps), "media");
    assert.equal(nextWizardStepId("media", chinaSimpleSteps), "pricing");
    assert.equal(nextWizardStepId("media", chinaVariantSteps), "variants");
    assert.equal(previousWizardStepId("media", chinaVariantSteps), "basic");
    assert.equal(nextWizardStepId("variants", tzVariantSteps), "review");
    assert.equal(nextWizardStepId("review", chinaSimpleSteps), null);
  });

  it("normalizes invalid persisted step ids", () => {
    assert.equal(normalizeWizardStepId("unknown", chinaSimpleSteps), "basic");
    assert.equal(normalizeWizardStepId("shipping", chinaSimpleSteps), "shipping");
    assert.equal(normalizeWizardStepId("pricing", tzVariantSteps), "basic");
  });

  it("infers pricing model for existing drafts", () => {
    assert.equal(
      inferProductCreationPricingModel({
        persistedPricingModel: "variant",
        variantCount: 0,
        sellableVariantCount: 0,
        price: 0,
        hasSimpleInventoryPolicy: true,
      }),
      "variants",
    );
    assert.equal(
      inferProductCreationPricingModel({
        persistedPricingModel: "simple",
        variantCount: 2,
        sellableVariantCount: 0,
        price: 0,
        hasSimpleInventoryPolicy: false,
      }),
      "simple",
    );
    assert.equal(
      inferProductCreationPricingModel({
        variantCount: 2,
        sellableVariantCount: 0,
        price: 0,
        hasSimpleInventoryPolicy: false,
      }),
      "variants",
    );
    assert.equal(
      inferProductCreationPricingModel({
        variantCount: 0,
        sellableVariantCount: 0,
        price: 15000,
        hasSimpleInventoryPolicy: false,
      }),
      "simple",
    );
  });

  it("maps api and wizard pricing model values", () => {
    assert.equal(mapWizardPricingModelToApi("variants"), "variant");
    assert.equal(mapWizardPricingModelToApi("simple"), "simple");
    assert.equal(mapApiPricingModelToWizard("variant"), "variants");
    assert.equal(mapApiPricingModelToWizard("simple"), "simple");
    assert.equal(resolveWizardPricingModelFromProduct("variant"), "variants");
    assert.equal(resolveWizardPricingModelFromProduct("simple"), "simple");
    assert.equal(resolveWizardPricingModelFromProduct(null), "simple");
  });

  it("resumes variant and simple drafts from persisted pricing model", () => {
    assert.deepEqual(
      resolveProductCreationWizardSteps(
        "tz",
        resolveWizardPricingModelFromProduct("variant"),
      ).map((step) => step.id),
      ["basic", "media", "variants", "review"],
    );
    assert.deepEqual(
      resolveProductCreationWizardSteps(
        "china",
        resolveWizardPricingModelFromProduct("simple"),
      ).map((step) => step.id),
      ["basic", "media", "pricing", "shipping", "china-import", "review"],
    );
  });

  it("zeros simple pricing fields in save payload for variant intent", () => {
    assert.deepEqual(wizardSavePricingFields("variants", 25000, 10000), {
      price: 0,
      cost_price: null,
    });
    assert.deepEqual(wizardSavePricingFields("simple", 25000, 10000), {
      price: 25000,
      cost_price: 10000,
    });
  });

  it("calculates draft progress and missing publish labels", () => {
    const progress = calculateProductCreationWizardProgress({
      steps: chinaSimpleSteps,
      form: {
        id: "prod-1",
        ...basicFormFields,
        commerceJourney: "china",
        pricingModel: "simple",
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
    assert.equal(
      isWizardStepComplete("basic", {
        form: {
          id: "prod-1",
          ...basicFormFields,
          commerceJourney: "china",
          pricingModel: "simple",
        },
        mediaCount: 0,
        hasPrimaryImage: false,
        variantCount: 0,
        sellableVariantCount: 0,
        hasPublishableShipping: false,
        publishReadiness: null,
      }),
      true,
    );
  });

  it("requires supplier on china-import step", () => {
    assert.match(
      validateWizardStepBeforeContinue("china-import", {
        id: "p1",
        ...basicFormFields,
        commerceJourney: "china",
        pricingModel: "variants",
        price: 1000,
      }) ?? "",
      /supplier/i,
    );
  });

  it("uses wizard flow for new and draft products only", () => {
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: true, status: "draft" }), true);
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: false, status: "draft" }), true);
    assert.equal(shouldUseProductCreationWizard({ isNewProduct: false, status: "active" }), false);
  });
});
