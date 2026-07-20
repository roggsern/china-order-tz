"use client";

import { useCallback, useMemo, useState } from "react";
import type { Category, Product } from "@/lib/types/catalog";
import { pickProductShippingContext } from "@/lib/types/catalog";
import { useMoqDisplayState } from "@/lib/catalog/use-moq-display";
import { ProductDetailHeader } from "./ProductDetailHeader";
import { ProductPriceSection } from "./ProductPriceSection";
import {
  ShippingEstimator,
  getDefaultShippingMethod,
  getShippingCostForMethod,
  type ShippingMethodSelection,
} from "./ShippingEstimator";
import { LandedCostSummary } from "./LandedCostSummary";
import { DeliveryEstimator } from "./DeliveryEstimator";
import { ProductOriginBanner } from "./ProductOriginBanner";
import { QuantitySelector } from "./QuantitySelector";
import { AddToCartButton } from "./AddToCartButton";
import { BuyNowButton } from "./BuyNowButton";
import { WishlistButton } from "./WishlistButton";
import { ProductPurchaseTrust } from "./ProductPurchaseTrust";
import { TrustBadges } from "./TrustBadges";
import { getCatalogProductImageSrc } from "@/lib/catalog/product-images";
import {
  ProductConfigurationPicker,
  type StorefrontConfigurationSelection,
} from "./ProductConfigurationPicker";
import { ProductMoqStatusCard } from "./ProductMoqStatusCard";
import type { StorefrontPriceQuote } from "@/lib/catalog/storefront-configuration";

interface ProductDetailPurchasePanelProps {
  product: Product;
  category?: Category;
  onSelectedColorChange?: (colorSlug: string | null) => void;
}

export function ProductDetailPurchasePanel({
  product,
  category,
  onSelectedColorChange,
}: ProductDetailPurchasePanelProps) {
  const [quantity, setQuantity] = useState(1);
  const [quantityMax, setQuantityMax] = useState(Math.min(product.stock || 99, 99));
  const [configSelection, setConfigSelection] = useState<StorefrontConfigurationSelection>({
    configurationId: null,
    label: "",
    sku: "",
    inStock: true,
    stock: product.stock,
    isComplete: false,
    hasConfigurations: false,
    selectedAttributes: [],
    selectedColorSlug: null,
  });
  const [quote, setQuote] = useState<StorefrontPriceQuote | null>(null);

  const shippingContext = pickProductShippingContext(product);
  const [selectedMethod, setSelectedMethod] = useState<ShippingMethodSelection>(() =>
    getDefaultShippingMethod(shippingContext),
  );

  const handleSelectionChange = useCallback((selection: StorefrontConfigurationSelection) => {
    setConfigSelection(selection);
  }, []);

  const handleQuoteChange = useCallback((nextQuote: StorefrontPriceQuote | null) => {
    setQuote(nextQuote);
  }, []);

  const handleQuantityMaxChange = useCallback((max: number) => {
    setQuantityMax(Math.min(Math.max(1, max), 99));
    setQuantity((current) => Math.min(current, Math.max(1, max)));
  }, []);

  const unitPrice = quote
    ? Number.parseFloat(quote.unit_price)
    : product.price;

  const needsConfiguration =
    configSelection.hasConfigurations && !configSelection.isComplete;
  const configurationOutOfStock =
    configSelection.hasConfigurations &&
    configSelection.isComplete &&
    !configSelection.inStock;
  const isOutOfStock =
    (!configSelection.hasConfigurations && product.stock <= 0) || configurationOutOfStock;
  const canAdd =
    !isOutOfStock &&
    !needsConfiguration &&
    (!configSelection.hasConfigurations || Boolean(configSelection.configurationId));
  const orderSummaryReady = !needsConfiguration;

  const moqDisplay = useMoqDisplayState({
    quote,
    slug: product.slug,
    configurationId: configSelection.configurationId,
    quantity,
    stock: configSelection.hasConfigurations ? configSelection.stock : product.stock,
    enabled: orderSummaryReady && Boolean(configSelection.configurationId),
  });
  const compareAtUnitPrice =
    moqDisplay.wholesaleApplied
      ? moqDisplay.compareAtUnitPrice ?? undefined
      : undefined;

  const shippingCost = useMemo(
    () =>
      orderSummaryReady
        ? getShippingCostForMethod(shippingContext, selectedMethod, quantity)
        : null,
    [shippingContext, selectedMethod, quantity, orderSummaryReady],
  );

  return (
    <div className="min-w-0 lg:sticky lg:top-20 lg:self-start">
      <div className="space-y-6 rounded-3xl border border-zinc-100 bg-white p-6 shadow-[0_8px_40px_rgba(0,0,0,0.06)] lg:space-y-5 lg:p-5">
        <ProductDetailHeader product={product} category={category} />

        {!configSelection.hasConfigurations ? (
          <ProductPriceSection price={product.price} oldPrice={product.oldPrice} />
        ) : null}

        {product.trustBadges.length > 0 && (
          <TrustBadges badges={product.trustBadges} size="md" />
        )}

        <ProductOriginBanner origin={product.origin} />

        <ProductConfigurationPicker
          productSlug={product.slug}
          basePrice={product.price}
          quantity={quantity}
          onQuantityMaxChange={handleQuantityMaxChange}
          onSelectionChange={handleSelectionChange}
          onQuoteChange={handleQuoteChange}
          onSelectedColorChange={onSelectedColorChange}
        />

        {needsConfiguration ? (
          <p className="text-sm font-medium text-amber-700" role="status">
            Select a complete configuration to continue.
          </p>
        ) : null}

        <div className="space-y-4 border-t border-zinc-100 pt-6">
          <QuantitySelector
            quantity={quantity}
            onChange={setQuantity}
            max={quantityMax}
            min={1}
          />
          <ProductMoqStatusCard
            unlocked={
              moqDisplay.wholesaleApplied && moqDisplay.moqDiscount > 0
                ? {
                    savingsAmount: moqDisplay.moqDiscount,
                    unitPrice: moqDisplay.unitPrice ?? unitPrice,
                  }
                : null
            }
            hint={moqDisplay.moqHint}
          />
        </div>

        {product.origin === "china" ? (
          <ShippingEstimator
            {...shippingContext}
            selectedMethod={selectedMethod}
            onSelect={setSelectedMethod}
            quantity={quantity}
            configurationIncomplete={needsConfiguration}
          />
        ) : (
          <div className="rounded-2xl border border-zinc-100 bg-zinc-50/50 p-5">
            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
              Shipping
            </p>
            {needsConfiguration ? (
              <p className="mt-3 text-sm font-medium text-zinc-600" role="status">
                Complete your selection to estimate shipping.
              </p>
            ) : (
              <DeliveryEstimator origin={product.origin} variant="detail" className="mt-4" />
            )}
          </div>
        )}

        <LandedCostSummary
          productPrice={unitPrice}
          compareAtUnitPrice={
            moqDisplay.wholesaleApplied ? moqDisplay.compareAtUnitPrice : null
          }
          shippingCost={shippingCost}
          quantity={quantity}
          shippingContext={shippingContext}
          isReady={orderSummaryReady}
          moqHint={moqDisplay.moqHint}
        />

        <div className="flex flex-col gap-3">
          <BuyNowButton
            product={product}
            quantity={quantity}
            disabled={!canAdd}
            className="w-full"
            configurationId={configSelection.configurationId}
            configurationLabel={configSelection.label}
            configurationSku={configSelection.sku}
            selectedAttributes={configSelection.selectedAttributes}
            quotedUnitPrice={unitPrice}
            compareAtUnitPrice={compareAtUnitPrice}
            stockOverride={
              configSelection.hasConfigurations ? configSelection.stock : undefined
            }
          />
          <div className="flex gap-3">
            <AddToCartButton
              product={product}
              quantity={quantity}
              variant="detail"
              disabled={!canAdd}
              configurationId={configSelection.configurationId}
              configurationLabel={configSelection.label}
              configurationSku={configSelection.sku}
              selectedAttributes={configSelection.selectedAttributes}
              quotedUnitPrice={unitPrice}
              compareAtUnitPrice={compareAtUnitPrice}
              stockOverride={
                configSelection.hasConfigurations
                  ? configSelection.stock
                  : undefined
              }
              className="flex-1"
            />
            <WishlistButton
              className="shrink-0"
              productId={product.id}
              slug={product.slug}
              name={product.name}
              imageUrl={getCatalogProductImageSrc(product) || undefined}
              emoji={product.emoji}
              gradient={product.gradient}
              price={product.price}
            />
          </div>
        </div>

        <ProductPurchaseTrust />
      </div>
    </div>
  );
}
