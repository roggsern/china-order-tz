import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { clearSessionOnAuthFailure, useAuthStore } from '@/src/core/auth';
import {
  buildLoginHref,
  getCartErrorMessage,
  isCartUnauthenticatedError,
  useAddToCartMutation,
} from '@/src/features/cart';
import { journeyLabelFromChannel } from '@/src/features/cart/utils/journeyLabel';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { normalizeCustomerPlainText } from '@/src/shared/utils/normalizeCustomerPlainText';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { TrustStrip, type TrustStripItem } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
import {
  useProductDetail,
  useProductQuote,
} from '../hooks/useCatalogQueries';
import { buildProductHref } from '../map/journeyRoutes';
import type {
  CatalogProductDetail,
  ConfigurationSelections,
  ProductConfiguration,
  ProductDetailParams,
} from '../models/types';
import { canAddToCart, resolveAddToCartGate } from '../utils/canAddToCart';
import { resolvePdpGalleryImages } from '../utils/configurationOptions';
import { resolveDisplayedProductPrice } from '../utils/resolveDisplayedProductPrice';
import { AddToCartButton } from './AddToCartButton';
import { ProductAvailabilityBadge } from './ProductAvailabilityBadge';
import { ProductConfigurationSelector } from './ProductConfigurationSelector';
import { ProductImageGallery } from './ProductImageGallery';
import { ProductVariantsList } from './ProductVariantsList';
import { QuantitySelector } from './QuantitySelector';
import {
  CatalogEmptyState,
  CatalogErrorState,
  CatalogLoadingState,
} from './CatalogStateViews';

type Props = {
  productKey: string;
  journey: CommerceJourney;
  storeSlug?: string | null;
};

function buildTrustItems(params: {
  product: CatalogProductDetail;
  journey: CommerceJourney;
  storeSlug?: string | null;
}): TrustStripItem[] {
  const { product, journey, storeSlug } = params;
  const items: TrustStripItem[] = [
    {
      id: 'journey',
      title: journeyLabelFromChannel(product.commerceChannelCode ?? journey),
      description: product.commerceSourceLabel?.trim() || null,
    },
  ];

  const resolvedStore = storeSlug?.trim() || product.storeSlug?.trim() || null;
  if (resolvedStore) {
    items.push({
      id: 'store',
      title: 'Store context',
      description: resolvedStore,
    });
  }

  if (product.brand?.name) {
    items.push({
      id: 'brand',
      title: product.brand.name,
      description: 'Brand',
    });
  }

  if (
    product.shippingPrices &&
    (product.shippingPrices.air != null || product.shippingPrices.sea != null)
  ) {
    items.push({
      id: 'shipping',
      title: 'Shipping options available',
      description: [
        product.shippingPrices.air != null ? 'Air' : null,
        product.shippingPrices.sea != null ? 'Sea' : null,
      ]
        .filter(Boolean)
        .join(' · '),
    });
  }

  return items;
}

export function ProductDetailScreen({ productKey, journey, storeSlug }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const [quantity, setQuantity] = useState(1);
  const [selections, setSelections] = useState<ConfigurationSelections>({});
  const [liveConfiguration, setLiveConfiguration] =
    useState<ProductConfiguration | null>(null);
  const [configStatus, setConfigStatus] = useState({
    loading: true,
    error: false,
  });
  const [feedback, setFeedback] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  const detailParams: ProductDetailParams | null =
    journey === 'TZ_LOCAL' && !storeSlug
      ? null
      : {
          productKey,
          journey,
          storeSlug,
        };

  const detailQuery = useProductDetail(detailParams);
  const addToCartMutation = useAddToCartMutation();

  const matchedForQuote =
    configStatus.loading || configStatus.error
      ? null
      : liveConfiguration?.matchedConfigurationId ?? null;

  const quoteQuery = useProductQuote({
    productKey,
    configurationId: matchedForQuote,
    quantity,
    enabled: Boolean(matchedForQuote),
  });

  const handleConfigurationChange = useCallback(
    (configuration: ProductConfiguration | null) => {
      setLiveConfiguration(configuration);
    },
    [],
  );

  const handleConfigStatusChange = useCallback(
    (status: { loading: boolean; error: boolean }) => {
      setConfigStatus(status);
    },
    [],
  );

  if (journey === 'TZ_LOCAL' && !storeSlug) {
    return (
      <CatalogEmptyState
        title="Store required"
        message="Open this product from a TZ store catalog so the store context is known."
      />
    );
  }

  if (detailQuery.isLoading) {
    return <CatalogLoadingState label="Loading product…" />;
  }

  if (detailQuery.isError) {
    return (
      <CatalogErrorState
        error={detailQuery.error}
        onRetry={() => void detailQuery.refetch()}
      />
    );
  }

  const product = detailQuery.data;
  if (!product) {
    return (
      <CatalogEmptyState title="Product not found" message="No product data returned." />
    );
  }

  const detailProduct: CatalogProductDetail = product;
  const configuration = liveConfiguration;
  const hasConfigurations = Boolean(configuration?.hasConfigurations);
  const gate = resolveAddToCartGate({
    product: detailProduct,
    configuration,
    configurationLoading: configStatus.loading,
    configurationError: configStatus.error,
    quantity,
    submitting: addToCartMutation.isPending,
  });
  const enabled = gate.canAdd;

  const displayedPrice = resolveDisplayedProductPrice({
    product: detailProduct,
    configuration,
    configurationLoading: configStatus.loading,
    quote: quoteQuery.data ?? null,
    quoteLoading: Boolean(matchedForQuote) && quoteQuery.isFetching,
  });

  const galleryImages = resolvePdpGalleryImages({
    productImages: detailProduct.images,
    variants: detailProduct.variants,
    matchedConfigurationId:
      !configStatus.loading && configuration?.isComplete
        ? configuration.matchedConfigurationId
        : null,
  });

  const productId = detailProduct.id;
  const productSlug = detailProduct.slug;
  const matchedConfigurationId = configuration?.matchedConfigurationId ?? null;
  const showSale =
    detailProduct.compareAtPrice != null &&
    displayedPrice.source === 'base' &&
    displayedPrice.amount != null &&
    Number(detailProduct.compareAtPrice) > Number(displayedPrice.amount);

  const trustItems = buildTrustItems({
    product: detailProduct,
    journey,
    storeSlug,
  });

  async function handleAddToCart() {
    if (!canAddToCart({
      product: detailProduct,
      configuration,
      configurationLoading: configStatus.loading,
      configurationError: configStatus.error,
      quantity,
      submitting: addToCartMutation.isPending,
    })) {
      return;
    }

    setFeedback(null);

    const returnHref = buildProductHref({
      slug: productSlug,
      journey,
      storeSlug,
    });

    if (authStatus !== 'authenticated') {
      router.push(buildLoginHref(returnHref));
      return;
    }

    try {
      await addToCartMutation.mutateAsync({
        productId,
        productVariantId: hasConfigurations ? matchedConfigurationId : null,
        quantity,
        journey,
      });
      setFeedback({
        type: 'success',
        message: 'Added to cart.',
      });
    } catch (error) {
      if (isCartUnauthenticatedError(error)) {
        await clearSessionOnAuthFailure();
        router.push(buildLoginHref(returnHref));
        return;
      }
      setFeedback({
        type: 'error',
        message: getCartErrorMessage(error),
      });
    }
  }

  return (
    <ScreenContainer padded={false} scroll contentStyle={styles.content}>
      <ProductImageGallery images={galleryImages} />

      <View style={styles.body}>
        <View style={styles.badgeRow}>
          <Badge
            label={journey === 'TZ_LOCAL' ? 'Tanzania' : 'China'}
            tone={journey === 'TZ_LOCAL' ? 'success' : 'brand'}
          />
          {product.commerceSourceLabel ? (
            <Badge label={product.commerceSourceLabel} tone="neutral" />
          ) : null}
          {(storeSlug || product.storeSlug) ? (
            <Badge
              label={storeSlug || product.storeSlug || ''}
              tone="info"
            />
          ) : null}
          {product.brand?.name ? (
            <Badge label={product.brand.name} tone="neutral" />
          ) : null}
        </View>

        <Text style={styles.name}>{product.name}</Text>

        <View style={styles.priceBlock}>
          {displayedPrice.source === 'pending' ? (
            <Text style={styles.pricePending}>Checking price…</Text>
          ) : (
            <PriceText
              value={displayedPrice.amount}
              currency={displayedPrice.currency ?? 'TZS'}
              size="large"
            />
          )}
          {showSale ? (
            <PriceText
              value={detailProduct.compareAtPrice}
              accessibilityLabelPrefix="Was"
              style={styles.compare}
            />
          ) : null}
        </View>

        <ProductAvailabilityBadge
          product={detailProduct}
          configuration={configuration}
        />

        {detailProduct.description ? (
          <Card elevated={false} style={styles.descriptionCard}>
            <Text style={styles.sectionTitle}>About this product</Text>
            <Text style={styles.description}>
              {normalizeCustomerPlainText(detailProduct.description)}
            </Text>
          </Card>
        ) : null}

        {detailProduct.shippingPrices &&
        (detailProduct.shippingPrices.air != null ||
          detailProduct.shippingPrices.sea != null) ? (
          <Card elevated={false} style={styles.shippingCard}>
            <Text style={styles.sectionTitle}>Shipping options</Text>
            {detailProduct.shippingPrices.air != null ? (
              <Text style={styles.meta}>
                Air:{' '}
                {formatCustomerMoney(detailProduct.shippingPrices.air, 'TZS')}
              </Text>
            ) : null}
            {detailProduct.shippingPrices.sea != null ? (
              <Text style={styles.meta}>
                Sea:{' '}
                {formatCustomerMoney(detailProduct.shippingPrices.sea, 'TZS')}
              </Text>
            ) : null}
          </Card>
        ) : null}

        <ProductConfigurationSelector
          productKey={product.slug}
          selections={selections}
          onSelectionsChange={setSelections}
          onConfigurationChange={handleConfigurationChange}
          onStatusChange={handleConfigStatusChange}
        />

        <QuantitySelector
          quantity={quantity}
          onChange={setQuantity}
          disabled={addToCartMutation.isPending || !enabled}
        />

        <AddToCartButton
          enabled={enabled}
          submitting={addToCartMutation.isPending}
          label={gate.label}
          onPress={() => void handleAddToCart()}
        />

        {feedback ? (
          <Text
            style={
              feedback.type === 'success' ? styles.successText : styles.errorText
            }
          >
            {feedback.message}
          </Text>
        ) : null}

        {!hasConfigurations ? (
          <ProductVariantsList
            variants={product.variants}
            selectedVariantId={matchedConfigurationId}
          />
        ) : null}

        <TrustStrip items={trustItems} />
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: {
    paddingBottom: spacing.huge,
  },
  body: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.md,
  },
  name: {
    ...typography.heading,
    marginBottom: spacing.sm,
  },
  priceBlock: {
    flexDirection: 'row',
    alignItems: 'baseline',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginBottom: spacing.xs,
  },
  pricePending: {
    ...typography.priceLarge,
    color: colors.textMuted,
  },
  compare: {
    ...typography.caption,
    textDecorationLine: 'line-through',
    color: colors.textSubtle,
    fontWeight: '400',
  },
  descriptionCard: {
    marginTop: spacing.lg,
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  shippingCard: {
    marginTop: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  sectionTitle: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  description: {
    ...typography.body,
    color: colors.text,
  },
  meta: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
  successText: {
    marginTop: spacing.md,
    ...typography.bodyStrong,
    color: colors.success,
  },
  errorText: {
    marginTop: spacing.md,
    ...typography.body,
    color: colors.error,
  },
});
