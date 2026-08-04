import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  calculateProductCreationWizardProgress,
  canFreelyNavigateWizardSteps,
  canSelectWizardStep,
  inferProductCreationPricingModel,
  isWizardStepComplete,
  mapApiPricingModelToWizard,
  mapWizardPricingModelToApi,
  nextWizardStepId,
  normalizeWizardStepId,
  previousWizardStepId,
  resolveProductCreationWizardSteps,
  resolveWizardPricingModelFromProduct,
  resolveWizardStepStatus,
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

    // China simple actionable steps: basic, media, pricing, shipping, china-import (5).
    // Draft-only complete → 1/5 = 20%.
    assert.equal(progress.percent, 20);
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

  it("increases completion percent as media pricing shipping and china-import complete", () => {
    const base = {
      steps: chinaSimpleSteps,
      form: {
        id: "prod-1",
        ...basicFormFields,
        commerceJourney: "china" as const,
        pricingModel: "simple" as const,
        price: 0,
        supplierId: "",
      },
      mediaCount: 0,
      hasPrimaryImage: false,
      variantCount: 0,
      sellableVariantCount: 0,
      hasPublishableShipping: false,
      publishReadiness: null,
    };

    const draftOnly = calculateProductCreationWizardProgress(base);
    assert.equal(draftOnly.percent, 20);

    const withMedia = calculateProductCreationWizardProgress({
      ...base,
      mediaCount: 2,
      hasPrimaryImage: true,
    });
    assert.equal(withMedia.percent, 40);
    assert.ok(withMedia.percent > draftOnly.percent);

    const withPricing = calculateProductCreationWizardProgress({
      ...base,
      form: { ...base.form, price: 25000 },
      mediaCount: 2,
      hasPrimaryImage: true,
    });
    assert.equal(withPricing.percent, 60);

    const withShipping = calculateProductCreationWizardProgress({
      ...base,
      form: { ...base.form, price: 25000 },
      mediaCount: 2,
      hasPrimaryImage: true,
      hasPublishableShipping: true,
    });
    assert.equal(withShipping.percent, 80);

    const withChinaImport = calculateProductCreationWizardProgress({
      ...base,
      form: { ...base.form, price: 25000, supplierId: "sup-1" },
      mediaCount: 2,
      hasPrimaryImage: true,
      hasPublishableShipping: true,
    });
    assert.equal(withChinaImport.percent, 100);
    assert.ok(withChinaImport.percent > withShipping.percent);
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

  it("before draft only basic section is selectable", () => {
    assert.equal(canFreelyNavigateWizardSteps(undefined), false);
    assert.equal(canSelectWizardStep("basic", chinaSimpleSteps, undefined), true);
    assert.equal(canSelectWizardStep("media", chinaSimpleSteps, undefined), false);
    assert.equal(canSelectWizardStep("pricing", chinaSimpleSteps, ""), false);
    assert.equal(canSelectWizardStep("shipping", chinaSimpleSteps, undefined), false);
    assert.equal(canSelectWizardStep("review", chinaSimpleSteps, undefined), false);
  });

  it("after draft every journey section is selectable", () => {
    assert.equal(canFreelyNavigateWizardSteps("prod-1"), true);
    for (const step of chinaSimpleSteps) {
      assert.equal(canSelectWizardStep(step.id, chinaSimpleSteps, "prod-1"), true);
    }
    for (const step of tzVariantSteps) {
      assert.equal(canSelectWizardStep(step.id, tzVariantSteps, "prod-1"), true);
    }
    assert.equal(canSelectWizardStep("pricing", tzVariantSteps, "prod-1"), false);
    assert.equal(canSelectWizardStep("china-import", tzSimpleSteps, "prod-1"), false);
  });

  it("allows free jump order across unlocked sections", () => {
    const draftId = "prod-jump";
    const path: Array<(typeof chinaSimpleSteps)[number]["id"]> = [
      "media",
      "shipping",
      "pricing",
      "media",
    ];
    for (const stepId of path) {
      assert.equal(canSelectWizardStep(stepId, chinaSimpleSteps, draftId), true);
      assert.equal(normalizeWizardStepId(stepId, chinaSimpleSteps), stepId);
    }
  });

  it("keeps publish blocked while readiness is incomplete", () => {
    const progress = calculateProductCreationWizardProgress({
      steps: chinaSimpleSteps,
      form: {
        id: "prod-1",
        ...basicFormFields,
        commerceJourney: "china",
        pricingModel: "simple",
        price: 1000,
        supplierId: "sup-1",
      },
      mediaCount: 1,
      hasPrimaryImage: true,
      variantCount: 0,
      sellableVariantCount: 0,
      hasPublishableShipping: true,
      publishReadiness: {
        ready: false,
        items: [],
        missing: [{ id: "inventory", label: "Inventory policy configured", met: false }],
        completed: [],
        path: "simple",
      },
    });
    assert.equal(progress.readyToPublish, false);
    assert.equal(
      isWizardStepComplete("review", {
        form: {
          id: "prod-1",
          ...basicFormFields,
          commerceJourney: "china",
          pricingModel: "simple",
        },
        mediaCount: 1,
        hasPrimaryImage: true,
        variantCount: 0,
        sellableVariantCount: 0,
        hasPublishableShipping: true,
        publishReadiness: {
          ready: false,
          items: [],
          missing: [{ id: "inventory", label: "Inventory policy configured", met: false }],
          completed: [],
          path: "simple",
        },
      }),
      false,
    );
  });

  it("marks section statuses from draft and content rules", () => {
    const emptyDraft = {
      form: {
        id: "prod-1",
        ...basicFormFields,
        commerceJourney: "china" as const,
        pricingModel: "simple" as const,
      },
      mediaCount: 0,
      hasPrimaryImage: false,
      variantCount: 0,
      sellableVariantCount: 0,
      hasPublishableShipping: false,
      publishReadiness: null,
    };
    assert.equal(resolveWizardStepStatus("basic", emptyDraft), "complete");
    assert.equal(resolveWizardStepStatus("media", emptyDraft), "not_started");
    assert.equal(resolveWizardStepStatus("pricing", emptyDraft), "not_started");

    assert.equal(
      resolveWizardStepStatus("media", {
        ...emptyDraft,
        mediaCount: 1,
        hasPrimaryImage: false,
      }),
      "incomplete",
    );
    assert.equal(
      resolveWizardStepStatus("variants", {
        ...emptyDraft,
        form: { ...emptyDraft.form, pricingModel: "variants" },
        variantCount: 0,
      }),
      "not_started",
    );
    assert.equal(
      resolveWizardStepStatus("variants", {
        ...emptyDraft,
        form: { ...emptyDraft.form, pricingModel: "variants" },
        variantCount: 6,
      }),
      "complete",
    );
  });

  it("hides china-only and pricing sections for tz variant journey", () => {
    assert.equal(
      tzVariantSteps.some((step) => step.id === "china-import"),
      false,
    );
    assert.equal(
      tzVariantSteps.some((step) => step.id === "pricing"),
      false,
    );
    assert.equal(
      chinaSimpleSteps.some((step) => step.id === "china-import"),
      true,
    );
  });
});
