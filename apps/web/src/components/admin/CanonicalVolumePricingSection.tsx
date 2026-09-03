"use client";

import { WholesalePricingEditor } from "@/components/admin/WholesalePricingEditor";
import {
  formatRetailTzsFromMinor,
  initialVolumePricingView,
  productVolumeAllowedTierTypes,
  variantVolumeLabel,
  volumePricingContextNotes,
  type VolumePriceShape,
  type VolumePricingView,
} from "@/lib/admin/volume-pricing-shape";
import { type VariantVolumeDraft } from "@/lib/admin/volume-pricing-tiers";
import type { AdminProductVariant } from "@/lib/api/admin-catalog";
import type { ProductPriceTierDraft } from "@/lib/types/catalog";

export type CanonicalVolumePricingSectionProps = {
  shape: VolumePriceShape;
  shapeLoaded: boolean;
  variants: AdminProductVariant[];
  variantRetailMinor: Record<string, number | null>;
  productEnabled: boolean;
  productTiers: ProductPriceTierDraft[];
  onProductEnabledChange: (enabled: boolean) => void;
  onProductTiersChange: (tiers: ProductPriceTierDraft[]) => void;
  productBasePrice: number;
  productEditorDisabled: boolean;
  volumeView: VolumePricingView;
  onVolumeViewChange: (view: VolumePricingView) => void;
  onKeepExisting: () => void;
  onReplaceWithPercent: () => void;
  onMoveToVariantPricing: () => void;
  onClearProductSchedule: () => void;
  variantDrafts: Record<string, VariantVolumeDraft>;
  onVariantDraftChange: (variantId: string, patch: Partial<VariantVolumeDraft>) => void;
  hasConfigurationPriceTiers: boolean;
  onClearActionError: () => void;
};

function relevantVariants(
  variants: AdminProductVariant[],
  ids: string[],
): AdminProductVariant[] {
  const allowed = new Set(ids);
  return variants.filter((variant) => allowed.has(variant.id));
}

export function CanonicalVolumePricingSection({
  shape,
  shapeLoaded,
  variants,
  variantRetailMinor,
  productEnabled,
  productTiers,
  onProductEnabledChange,
  onProductTiersChange,
  productBasePrice,
  productEditorDisabled,
  volumeView,
  onVolumeViewChange,
  onKeepExisting,
  onReplaceWithPercent,
  onMoveToVariantPricing,
  onClearProductSchedule,
  variantDrafts,
  onVariantDraftChange,
  hasConfigurationPriceTiers,
  onClearActionError,
}: CanonicalVolumePricingSectionProps) {
  const needsModeChoice =
    shapeLoaded &&
    (shape.kind === "configurable_different_retail" || shape.kind === "unknown");
  const allowedProductTypes = productVolumeAllowedTierTypes(shape);
  const notes = volumePricingContextNotes({
    shape,
    view: volumeView,
    hasVariantSchedules: hasConfigurationPriceTiers,
    hasProductSchedule: productEnabled && productTiers.length > 0,
  });
  const visibleVariants = relevantVariants(variants, shape.relevantVariantIds);
  const editorDisabled = productEditorDisabled || !shapeLoaded;

  if (shape.hasLegacyProductFixedUnitRisk && volumeView === "keep") {
    return (
      <section className="admin-card space-y-4 p-5">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">Bulk / Volume Pricing</h2>
          <p className="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
            {shape.note}
          </p>
        </div>
        <p className="text-xs text-zinc-600">
          Existing all-variant fixed prices are kept until you choose an action. Nothing is
          changed until you save.
        </p>
        <div className="flex flex-wrap gap-2">
          <button type="button" className="admin-btn-secondary" onClick={onKeepExisting}>
            Keep existing prices
          </button>
          <button type="button" className="admin-btn-secondary" onClick={onReplaceWithPercent}>
            Replace with percentage schedule
          </button>
          <button type="button" className="admin-btn-secondary" onClick={onMoveToVariantPricing}>
            Set bulk prices by variant
          </button>
          <button type="button" className="admin-btn-secondary" onClick={onClearProductSchedule}>
            Clear all-variant bulk pricing
          </button>
        </div>
        {notes.map((note) => (
          <p key={note} className="text-xs text-zinc-600">
            {note}
          </p>
        ))}
      </section>
    );
  }

  return (
    <div className="space-y-4">
      {needsModeChoice ? (
        <div className="admin-card space-y-3 p-5">
          <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
            {shape.note}
          </p>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className={
                volumeView === "product" ? "admin-btn-primary" : "admin-btn-secondary"
              }
              onClick={() => onVolumeViewChange("product")}
            >
              Percentage across all variants
            </button>
            <button
              type="button"
              className={
                volumeView === "variant" ? "admin-btn-primary" : "admin-btn-secondary"
              }
              onClick={() => onVolumeViewChange("variant")}
            >
              Set bulk prices by variant
            </button>
          </div>
          {shape.hasLegacyProductFixedUnitRisk ? (
            <p className="text-xs text-amber-800">
              An all-variant fixed unit schedule is still stored. Switching views does not delete
              it. Use Clear all-variant bulk pricing to remove it.
            </p>
          ) : null}
        </div>
      ) : null}

      {volumeView !== "variant" ? (
        <WholesalePricingEditor
          enabled={productEnabled}
          onEnabledChange={(enabled) => {
            onProductEnabledChange(enabled);
            onClearActionError();
          }}
          tiers={productTiers}
          onChange={onProductTiersChange}
          basePrice={productBasePrice}
          aggregatesVariants={shape.kind !== "simple"}
          disabled={editorDisabled}
          allowedTierTypes={allowedProductTypes}
          contextNotes={notes.filter((note) => note !== shape.note)}
          configurationTiersNote={
            hasConfigurationPriceTiers
              ? "This product also has variant-specific volume tiers. Saving here updates product-level thresholds only and does not delete those variant rows."
              : null
          }
        />
      ) : (
        <section className="admin-card space-y-4 p-5">
          <div>
            <h2 className="text-sm font-semibold text-zinc-900">Bulk / Volume Pricing</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Each variant has its own bulk schedule. Combined cart quantity still counts toward
              these thresholds.
            </p>
          </div>
          {visibleVariants.map((variant) => {
            const draft = variantDrafts[variant.id] ?? { enabled: false, tiers: [] };
            const retail = formatRetailTzsFromMinor(variantRetailMinor[variant.id]);
            const identity = variantVolumeLabel(variant);
            const title = retail ? `${identity} — Retail ${retail}` : identity;
            return (
              <div
                key={variant.id}
                className="rounded-xl border border-zinc-200 bg-white p-4"
              >
                <WholesalePricingEditor
                  enabled={draft.enabled}
                  onEnabledChange={(enabled) => {
                    onVariantDraftChange(variant.id, { enabled });
                    onClearActionError();
                  }}
                  tiers={draft.tiers}
                  onChange={(tiers) => onVariantDraftChange(variant.id, { tiers })}
                  basePrice={
                    variantRetailMinor[variant.id] != null
                      ? variantRetailMinor[variant.id]! / 100
                      : productBasePrice
                  }
                  title={title}
                  description="Quantity thresholds for this variant only. Sibling variants are not overwritten."
                  aggregatesVariants
                  disabled={editorDisabled}
                  embedded
                />
              </div>
            );
          })}
          {notes.map((note) => (
            <p key={note} className="text-xs text-zinc-600">
              {note}
            </p>
          ))}
          {productTiers.length > 0 ? (
            <button
              type="button"
              className="admin-btn-secondary"
              onClick={onClearProductSchedule}
            >
              Clear all-variant bulk pricing
            </button>
          ) : null}
        </section>
      )}
    </div>
  );
}

export function defaultVolumeViewForShape(
  shape: VolumePriceShape,
  hasVariantSchedules: boolean,
): VolumePricingView {
  return initialVolumePricingView({ shape, hasVariantSchedules });
}
