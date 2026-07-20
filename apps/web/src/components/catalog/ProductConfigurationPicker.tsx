"use client";

import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { formatPrice } from "@/lib/catalog/utils";
import {
  fetchStorefrontConfiguration,
  fetchStorefrontQuote,
  StorefrontConfigurationApiError,
  type StorefrontConfigurationExperience,
  type StorefrontPriceQuote,
} from "@/lib/catalog/storefront-configuration";

export type StorefrontConfigurationSelection = {
  configurationId: string | null;
  label: string;
  sku: string;
  inStock: boolean;
  stock: number;
  isComplete: boolean;
  hasConfigurations: boolean;
  selectedAttributes: Array<{ name: string; value: string; slug?: string | null }>;
  selectedColorSlug: string | null;
};

interface ProductConfigurationPickerProps {
  productSlug: string;
  basePrice: number;
  quantity: number;
  onQuantityMaxChange?: (max: number) => void;
  onSelectionChange: (selection: StorefrontConfigurationSelection) => void;
  onQuoteChange: (quote: StorefrontPriceQuote | null) => void;
  onSelectedColorChange?: (colorSlug: string | null) => void;
}

function resolveMoqSavings(quote: StorefrontPriceQuote | null) {
  if (!quote) return null;

  const tier = quote.breakdown.find(
    (stage) => stage.stage === "quantity_tier" && stage.applied,
  );
  if (!tier) return null;

  const preTier =
    quote.breakdown.find(
      (stage) =>
        stage.applied &&
        (stage.stage === "configuration_override" || stage.stage === "base"),
    ) ??
    quote.breakdown.find(
      (stage) =>
        stage.stage === "configuration_override" || stage.stage === "base",
    );

  const before = Number.parseFloat(preTier?.unit_price ?? quote.unit_price);
  const after = Number.parseFloat(quote.unit_price);
  if (!Number.isFinite(before) || !Number.isFinite(after) || after >= before) {
    return null;
  }

  const perUnit = before - after;
  const total = perUnit * quote.quantity;

  return {
    perUnit,
    total,
    before,
    after,
    minQuantity:
      typeof tier.meta?.min_quantity === "number" ? tier.meta.min_quantity : null,
    label: tier.label,
  };
}

export function ProductConfigurationPicker({
  productSlug,
  basePrice: _basePrice,
  quantity,
  onQuantityMaxChange,
  onSelectionChange,
  onQuoteChange,
  onSelectedColorChange,
}: ProductConfigurationPickerProps) {
  void _basePrice;
  const reduceMotion = useReducedMotion();
  const [experience, setExperience] = useState<StorefrontConfigurationExperience | null>(null);
  const [selections, setSelections] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [quote, setQuote] = useState<StorefrontPriceQuote | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const next = await fetchStorefrontConfiguration(productSlug, selections);
        if (cancelled) return;
        setExperience(next);
      } catch (err) {
        if (!cancelled) {
          setExperience(null);
          setError(
            err instanceof StorefrontConfigurationApiError
              ? err.message
              : "Unable to load product configurations.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [productSlug, selections]);

  const configAttributes = useMemo(
    () =>
      (experience?.attributes ?? []).filter(
        (attribute) => attribute.participates_in_configuration,
      ),
    [experience],
  );

  const matched = useMemo(() => {
    if (!experience?.matched_configuration_id) return null;
    return (
      experience.configurations.find(
        (row) => row.id === experience.matched_configuration_id,
      ) ?? null
    );
  }, [experience]);

  const selectedAttributes = useMemo(() => {
    return configAttributes.flatMap((attribute) => {
      const valueId = selections[attribute.id];
      if (!valueId) return [];
      const value = attribute.values.find((entry) => entry.id === valueId);
      if (!value) return [];
      return [
        {
          name: attribute.name,
          value: value.value,
          slug: value.slug ?? null,
        },
      ];
    });
  }, [configAttributes, selections]);

  const selectedColorSlug = useMemo(() => {
    const colorAttribute = configAttributes.find(
      (attribute) => attribute.type === "color" || attribute.slug === "color",
    );
    if (!colorAttribute) return null;
    const valueId = selections[colorAttribute.id];
    if (!valueId) return null;
    return colorAttribute.values.find((entry) => entry.id === valueId)?.slug ?? null;
  }, [configAttributes, selections]);

  useEffect(() => {
    onSelectedColorChange?.(selectedColorSlug);
  }, [selectedColorSlug, onSelectedColorChange]);

  useEffect(() => {
    const hasConfigurations = Boolean(experience?.has_configurations);
    const configurationId = experience?.matched_configuration_id ?? null;
    const inStock = Boolean(experience?.is_in_stock);
    const stock = matched?.stock ?? 0;

    onSelectionChange({
      configurationId,
      label: matched?.name ?? "",
      sku: matched?.sku ?? "",
      inStock,
      stock,
      isComplete: Boolean(experience?.is_complete && configurationId),
      hasConfigurations,
      selectedAttributes,
      selectedColorSlug,
    });

    if (hasConfigurations) {
      onQuantityMaxChange?.(Math.max(1, stock || 1));
    }
  }, [
    experience,
    matched,
    onQuantityMaxChange,
    onSelectionChange,
    selectedAttributes,
    selectedColorSlug,
  ]);

  useEffect(() => {
    let cancelled = false;

    async function loadQuote() {
      if (!experience?.has_configurations) {
        setQuote(null);
        onQuoteChange(null);
        return;
      }

      if (!experience.matched_configuration_id || !experience.is_in_stock) {
        setQuote(null);
        onQuoteChange(null);
        return;
      }

      try {
        const nextQuote = await fetchStorefrontQuote({
          slug: productSlug,
          configurationId: experience.matched_configuration_id,
          quantity,
        });
        if (cancelled) return;
        setQuote(nextQuote);
        onQuoteChange(nextQuote);
      } catch (err) {
        if (!cancelled) {
          setQuote(null);
          onQuoteChange(null);
          setError(
            err instanceof StorefrontConfigurationApiError
              ? err.message
              : "Unable to load live price.",
          );
        }
      }
    }

    void loadQuote();

    return () => {
      cancelled = true;
    };
  }, [
    experience?.has_configurations,
    experience?.matched_configuration_id,
    experience?.is_in_stock,
    productSlug,
    quantity,
    onQuoteChange,
  ]);

  const selectValue = (attributeId: string, valueId: string) => {
    setSelections((prev) => {
      const next = { ...prev };
      if (next[attributeId] === valueId) {
        delete next[attributeId];
      } else {
        next[attributeId] = valueId;
      }
      return next;
    });
  };

  if (loading && !experience) {
    return (
      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/60 p-4 text-sm text-zinc-500">
        Loading configurations…
      </div>
    );
  }

  if (error && !experience) {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {error}
      </div>
    );
  }

  if (!experience?.has_configurations) {
    return null;
  }

  const moqSavings = resolveMoqSavings(quote);
  const configurationOutOfStock = Boolean(
    experience.is_complete && matched && !matched.in_stock,
  );

  return (
    <div className="space-y-5 border-t border-zinc-100 pt-6">
      <div className="flex items-end justify-between gap-3">
        <div>
          {experience.product_type ? (
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-500">
              {experience.product_type.name} configuration
            </p>
          ) : null}
          <h3 className="mt-1 text-base font-bold text-zinc-900">Choose your options</h3>
        </div>
        {matched?.sku ? (
          <p className="truncate text-[11px] font-medium text-zinc-400">{matched.sku}</p>
        ) : null}
      </div>

      {configAttributes.map((attribute) => {
        const selected = selections[attribute.id];
        const visibleValues = attribute.values.filter((value) =>
          experience.configurations.some((row) =>
            row.attribute_value_ids.includes(value.id),
          ),
        );

        return (
          <div key={attribute.id} className="space-y-2.5">
            <div className="flex items-baseline justify-between gap-2">
              <p className="text-sm font-semibold text-zinc-900">
                {attribute.name}
                {attribute.unit ? (
                  <span className="ml-1.5 text-xs font-medium text-zinc-400">
                    ({attribute.unit})
                  </span>
                ) : null}
              </p>
              {selected ? (
                <p className="text-xs font-semibold text-[#8b6914]">
                  {visibleValues.find((value) => value.id === selected)?.value}
                </p>
              ) : (
                <p className="text-xs text-zinc-400">Select {attribute.name.toLowerCase()}</p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {visibleValues.map((value) => {
                const enabled = (experience.allowed_value_ids[attribute.id] ?? []).includes(
                  value.id,
                );
                const isSelected = selected === value.id;
                const disabled = !enabled && !isSelected;
                const isColor = attribute.type === "color" || Boolean(value.color_code);

                return (
                  <motion.button
                    key={value.id}
                    type="button"
                    disabled={disabled}
                    onClick={() => selectValue(attribute.id, value.id)}
                    whileTap={reduceMotion || disabled ? undefined : { scale: 0.98 }}
                    animate={
                      reduceMotion
                        ? undefined
                        : {
                            scale: isSelected ? 1.02 : 1,
                            y: isSelected ? -1 : 0,
                          }
                    }
                    transition={{ type: "spring", stiffness: 420, damping: 28 }}
                    className={`relative overflow-hidden rounded-2xl border px-3.5 py-3.5 text-left transition-all duration-200 ${
                      isSelected
                        ? "border-[#c9a227] bg-gradient-to-br from-[#c9a227]/18 via-white to-white shadow-[0_10px_28px_rgba(201,162,39,0.2)]"
                        : disabled
                          ? "cursor-not-allowed border-zinc-100 bg-zinc-50/80 opacity-55"
                          : "border-zinc-200 bg-white hover:-translate-y-0.5 hover:border-[#c9a227]/35 hover:shadow-[0_8px_22px_rgba(0,0,0,0.06)]"
                    }`}
                    aria-pressed={isSelected}
                    aria-disabled={disabled}
                  >
                    {isSelected ? (
                      <span className="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-[#c9a227] text-[10px] font-bold text-white">
                        ✓
                      </span>
                    ) : null}

                    <div className="flex items-center gap-2.5 pr-4">
                      {isColor && value.color_code ? (
                        <span
                          className={`h-8 w-8 shrink-0 rounded-full border shadow-inner ${
                            disabled ? "border-zinc-200 grayscale" : "border-zinc-300"
                          }`}
                          style={{ backgroundColor: value.color_code }}
                          aria-hidden
                        />
                      ) : null}
                      <div className="min-w-0">
                        <p
                          className={`truncate text-sm font-semibold ${
                            disabled ? "text-zinc-400 line-through" : "text-zinc-900"
                          }`}
                        >
                          {value.value}
                        </p>
                        {disabled ? (
                          <p className="mt-0.5 text-[11px] font-medium text-zinc-400">
                            Unavailable
                          </p>
                        ) : isSelected ? (
                          <p className="mt-0.5 text-[11px] font-semibold text-[#8b6914]">
                            Selected
                          </p>
                        ) : (
                          <p className="mt-0.5 text-[11px] text-zinc-400">Tap to choose</p>
                        )}
                      </div>
                    </div>
                  </motion.button>
                );
              })}
            </div>
          </div>
        );
      })}

      <AnimatePresence mode="wait">
        {configurationOutOfStock ? (
          <motion.div
            key="oos"
            initial={reduceMotion ? false : { opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -4 }}
            className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3"
            role="status"
          >
            <p className="text-sm font-semibold text-red-700">
              This configuration is out of stock
            </p>
            <p className="mt-1 text-xs text-red-600/90">
              Try another Color or Storage combination — available options stay enabled.
            </p>
          </motion.div>
        ) : !experience.is_complete ? (
          <motion.p
            key="incomplete"
            initial={reduceMotion ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={reduceMotion ? undefined : { opacity: 0 }}
            className="text-sm text-zinc-500"
            role="status"
          >
            Select all options to see shipping and your order total.
          </motion.p>
        ) : matched?.in_stock ? (
          <motion.div
            key="selected"
            initial={reduceMotion ? false : { opacity: 0, y: 4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0 }}
            className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-3"
          >
            <div className="min-w-0">
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">
                Your selection
              </p>
              <p className="mt-1 truncate text-sm font-semibold text-zinc-900">
                {selectedAttributes.map((attribute) => attribute.value).join(" · ") ||
                  matched.name}
              </p>
            </div>
            <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
              {matched.stock} in stock
            </span>
          </motion.div>
        ) : null}
      </AnimatePresence>

      <AnimatePresence>
        {moqSavings ? (
          <motion.div
            key="moq"
            initial={reduceMotion ? false : { opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={reduceMotion ? undefined : { opacity: 0, height: 0 }}
            className="overflow-hidden"
          >
            <div className="rounded-xl bg-emerald-50 px-3 py-2.5 ring-1 ring-emerald-100">
              <p className="text-xs font-bold text-emerald-800">
                Volume savings applied
                {moqSavings.minQuantity ? ` · ${moqSavings.minQuantity}+ units` : ""}
              </p>
              <p className="mt-1 text-xs text-emerald-700">
                You save {formatPrice(moqSavings.perUnit)} per unit
                {moqSavings.total > moqSavings.perUnit
                  ? ` (${formatPrice(moqSavings.total)} total vs ${formatPrice(moqSavings.before)} each)`
                  : ""}
                .
              </p>
            </div>
          </motion.div>
        ) : null}
      </AnimatePresence>

      {error ? <p className="text-xs text-amber-700">{error}</p> : null}
    </div>
  );
}
