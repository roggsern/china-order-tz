import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveProductCreationWizardSteps } from "@/lib/admin/product-creation-wizard";
import {
  mapAdminApiCatalogProduct,
  type AdminApiProduct,
} from "@/lib/api/admin-catalog";

/** Mirrors AdminCatalogProductsPanel.openEdit() pricing model assignment after fix. */
function openEditPricingModel(product: ReturnType<typeof mapAdminApiCatalogProduct>) {
  return product.pricingModel;
}

function variantDraftApiProduct(): AdminApiProduct {
  return {
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    name: "Variant Draft",
    slug: "variant-draft",
    price: 0,
    pricing_model: "variant",
    lifecycle_status: "draft",
    commerce_channel: {
      id: "ch-tz",
      code: "TZ_LOCAL",
      name: "Buy From Tanzania",
    },
  };
}

describe("product draft reopen pricing model", () => {
  it("preserves variant wizard flow from API through openEdit", () => {
    const mapped = mapAdminApiCatalogProduct(variantDraftApiProduct());

    assert.equal(mapped.pricingModel, "variants");

    const pricingModel = openEditPricingModel(mapped);
    assert.equal(pricingModel, "variants");

    assert.deepEqual(
      resolveProductCreationWizardSteps("tz", pricingModel).map((step) => step.id),
      ["basic", "media", "variants", "review"],
    );
  });

  it("preserves simple wizard flow from API through openEdit", () => {
    const mapped = mapAdminApiCatalogProduct({
      ...variantDraftApiProduct(),
      pricing_model: "simple",
      price: 15000,
    });

    assert.equal(mapped.pricingModel, "simple");

    const pricingModel = openEditPricingModel(mapped);
    assert.equal(pricingModel, "simple");

    assert.deepEqual(
      resolveProductCreationWizardSteps("china", pricingModel).map((step) => step.id),
      ["basic", "media", "pricing", "shipping", "china-import", "review"],
    );
  });
});
