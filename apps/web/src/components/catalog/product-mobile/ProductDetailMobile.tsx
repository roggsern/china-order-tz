"use client";

import { useCallback, useEffect, useState } from "react";
import { motion } from "framer-motion";
import type { Category, Product } from "@/lib/types/catalog";
import { pickProductShippingContext } from "@/lib/types/catalog";
import { trackRecentlyViewed } from "@/lib/catalog/recently-viewed";
import { useMoqDisplayState } from "@/lib/catalog/use-moq-display";
import { Breadcrumbs } from "../Breadcrumbs";
import { ProductDetailHeader } from "../ProductDetailHeader";
import { ProductPriceSection } from "../ProductPriceSection";
import { ProductOriginBanner } from "../ProductOriginBanner";
import { QuantitySelector } from "../QuantitySelector";
import { AddToCartButton } from "../AddToCartButton";
import { BuyNowButton } from "../BuyNowButton";
import { ProductPurchaseTrust } from "../ProductPurchaseTrust";
import { TrustBadges } from "../TrustBadges";
import {
  ProductConfigurationPicker,
  type StorefrontConfigurationSelection,
} from "../ProductConfigurationPicker";
import type { StorefrontPriceQuote } from "@/lib/catalog/storefront-configuration";
import { isProductPurchaseUnavailable } from "@/lib/catalog/product-availability";
import { ProductMoqStatusCard } from "../ProductMoqStatusCard";
import { BulkPricingPanel } from "../BulkPricingPanel";
import { ProductGalleryMobile } from "./ProductGalleryMobile";
import { ProductMobileTabs } from "./ProductMobileTabs";
import { ProductSupplierCard } from "./ProductSupplierCard";
import { ProductHorizontalScroll } from "./ProductHorizontalScroll";
import { RecentlyViewedProducts } from "./RecentlyViewedProducts";
import { ProductMobileStickyBar } from "./ProductMobileStickyBar";

interface ProductDetailMobileProps {
  product: Product;
  category: Category | undefined;
  relatedProducts: Product[];
}

export function ProductDetailMobile({
  product,
  category,
  relatedProducts,
}: ProductDetailMobileProps) {
  const [quantity, setQuantity] = useState(1);
  const [quantityMax, setQuantityMax] = useState(Math.min(Math.max(product.stock, 0), 99));
  const [selectedColorSlug, setSelectedColorSlug] = useState<string | null>(null);
  const [configSelection, setConfigSelection] = useState<StorefrontConfigurationSelection>({
    configurationId: null,
    mediaPreviewConfigurationId: null,
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

  const handleSelectionChange = useCallback((selection: StorefrontConfigurationSelection) => {
    setConfigSelection(selection);
  }, []);

  const handleQuoteChange = useCallback((nextQuote: StorefrontPriceQuote | null) => {
    setQuote(nextQuote);
  }, []);

  const handleSelectedColorChange = useCallback((colorSlug: string | null) => {
    setSelectedColorSlug(colorSlug);
  }, []);

  const handleQuantityMaxChange = useCallback((max: number) => {
    setQuantityMax(Math.min(Math.max(1, max), 99));
    setQuantity((current) => Math.min(current, Math.max(1, max)));
  }, []);

  const unitPrice = quote ? Number.parseFloat(quote.unit_price) : product.price;
  const lineTotal = quote ? Number.parseFloat(quote.line_total) : unitPrice * quantity;
  const needsConfiguration =
    configSelection.hasConfigurations && !configSelection.isComplete;
  const configurationOutOfStock =
    configSelection.hasConfigurations &&
    configSelection.isComplete &&
    !configSelection.inStock;
  const purchaseUnavailable = isProductPurchaseUnavailable(product);
  const isOutOfStock =
    !purchaseUnavailable &&
    ((!configSelection.hasConfigurations && product.stock <= 0) || configurationOutOfStock);
  const canAdd =
    !purchaseUnavailable &&
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
    enabled: orderSummaryReady,
  });
  const compareAtUnitPrice = moqDisplay.wholesaleApplied
    ? moqDisplay.compareAtUnitPrice ?? undefined
    : undefined;
  const displayPrice =
    configSelection.hasConfigurations && !orderSummaryReady
      ? product.price
      : unitPrice;
  const displayOldPrice =
    moqDisplay.wholesaleApplied && compareAtUnitPrice != null
      ? compareAtUnitPrice
      : product.oldPrice;

  useEffect(() => {
    trackRecentlyViewed(product);
  }, [product]);

  const similarProducts = relatedProducts.slice(0, 6);
  const frequentlyBought = [...relatedProducts].reverse().slice(0, 6);

  return (
    <div className="pb-32 lg:hidden">
      <div className="px-4 pt-3">
        <Breadcrumbs
          items={[
            { label: "Products", href: "/products" },
            ...(category
              ? [
                  { label: category.name, href: `/categories/${category.slug}` },
                  { label: product.name },
                ]
              : [{ label: product.name }]),
          ]}
        />
      </div>

      <div className="mt-2">
        <ProductGalleryMobile
          product={product}
          selectedColorSlug={selectedColorSlug}
          configurationId={configSelection.configurationId}
          mediaPreviewConfigurationId={configSelection.mediaPreviewConfigurationId}
        />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.32 }}
        className="space-y-5 px-4 pt-4"
      >
        <ProductDetailHeader product={product} category={category} />

        <ProductPriceSection price={displayPrice} oldPrice={displayOldPrice} />

        {product.trustBadges.length > 0 && (
          <TrustBadges badges={product.trustBadges} size="sm" />
        )}

        <div className="rounded-2xl border border-zinc-100 bg-white p-4 shadow-sm">
          <ProductConfigurationPicker
            productSlug={product.slug}
            basePrice={product.price}
            quantity={quantity}
            variantGalleries={product.variantGalleries}
            onQuantityMaxChange={handleQuantityMaxChange}
            onSelectionChange={handleSelectionChange}
            onQuoteChange={handleQuoteChange}
            onSelectedColorChange={handleSelectedColorChange}
          />

          <div className="mt-4 border-t border-zinc-100 pt-4">
            <QuantitySelector
              quantity={quantity}
              onChange={setQuantity}
              max={quantityMax}
              variant="mobile"
            />
          </div>

          {needsConfiguration ? (
            <p className="mt-3 text-sm font-medium text-amber-700" role="status">
              Select a complete configuration to continue.
            </p>
          ) : (
            <>
              <BulkPricingPanel
                className="mt-3"
                pricing={moqDisplay.volumePricing}
                showVariantAggregationNote={Boolean(configSelection.hasConfigurations)}
                showShippingNote
              />
              {moqDisplay.volumePricing ? null : (
                <ProductMoqStatusCard
                  className="mt-3"
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
              )}
            </>
          )}
        </div>

        <div className="space-y-2.5">
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
              configSelection.hasConfigurations ? configSelection.stock : undefined
            }
          />
        </div>

        <ProductOriginBanner origin={product.origin} />

        <ProductPurchaseTrust variant="compact" />

        <ProductMobileTabs
          productSlug={product.slug}
          description={product.description}
          features={product.features}
          specifications={product.specifications}
          reviews={product.customerReviews}
          reviewCount={product.reviews}
          averageRating={product.rating}
          shippingContext={shippingContext}
          origin={product.origin}
        />

        <ProductSupplierCard
          origin={product.origin}
          brand={product.brand}
          trustBadges={product.trustBadges}
          rating={product.rating}
          reviewCount={product.reviews}
        />
      </motion.div>

      <div className="mt-6 space-y-6">
        <RecentlyViewedProducts currentProductId={product.id} />

        {frequentlyBought.length > 0 && (
          <ProductHorizontalScroll
            title="Complete Your Order"
            subtitle="Frequently bought together"
            products={frequentlyBought}
          />
        )}

        {similarProducts.length > 0 && (
          <ProductHorizontalScroll
            title="You May Also Like"
            subtitle="Similar products"
            products={similarProducts}
          />
        )}
      </div>

      <ProductMobileStickyBar
        product={product}
        quantity={quantity}
        disabled={!canAdd}
        purchaseUnavailable={purchaseUnavailable}
        configurationId={configSelection.configurationId}
        configurationLabel={configSelection.label}
        configurationSku={configSelection.sku}
        selectedAttributes={configSelection.selectedAttributes}
        quotedUnitPrice={unitPrice}
        compareAtUnitPrice={compareAtUnitPrice}
        lineTotal={lineTotal}
        needsConfiguration={needsConfiguration}
        isOutOfStock={isOutOfStock}
        stockOverride={
          configSelection.hasConfigurations ? configSelection.stock : undefined
        }
      />
    </div>
  );
}
