import { useCallback, useMemo, useState } from 'react';
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
import { useWishlistToggle } from '@/src/features/wishlist';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { Badge } from '@/src/shared/ui/Badge';
import { PriceText } from '@/src/shared/ui/PriceText';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip, type TrustStripItem } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
import {
  useProductDetail,
  useProductQuote,
} from '../hooks/useCatalogQueries';
import { useProductReviews } from '../hooks/useProductReviews';
import { buildProductHref } from '../map/journeyRoutes';
import type {
  CatalogProductDetail,
  ConfigurationSelections,
  ProductConfiguration,
  ProductDetailParams,
} from '../models/types';
import { canAddToCart, resolveAddToCartGate } from '../utils/canAddToCart';
import { resolveDisplayedProductPrice } from '../utils/resolveDisplayedProductPrice';
import {
  buildVariantGalleries,
  resolveMediaPreviewConfigurationId,
} from '../utils/resolveMediaPreview';
import { resolvePdpGalleryMedia } from '../utils/resolvePdpGalleryMedia';
import { AddToCartButton } from './AddToCartButton';
import { ProductAvailabilityBadge } from './ProductAvailabilityBadge';
import { ProductConfigurationSelector } from './ProductConfigurationSelector';
import { ProductImageGallery } from './ProductImageGallery';
import { ProductInfoSections } from './ProductInfoSections';
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
  const productId = detailQuery.data?.id ?? null;
  const productSlug = detailQuery.data?.slug ?? null;
  const reviewsQuery = useProductReviews(productSlug);
  const wishlist = useWishlistToggle(productId);

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

  const variantGalleries = useMemo(
    () => buildVariantGalleries(detailQuery.data?.variants ?? []),
    [detailQuery.data?.variants],
  );

  const storeRequired = journey === 'TZ_LOCAL' && !storeSlug;
  const product = detailQuery.data;

  if (storeRequired) {
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

  const exactConfigurationId =
    !configStatus.loading && configuration?.isComplete
      ? configuration.matchedConfigurationId
      : null;

  const mediaPreviewConfigurationId =
    !configStatus.loading && configuration
      ? resolveMediaPreviewConfigurationId({
          configurations: configuration.configurations,
          selections,
          attributes: configuration.attributes,
          variantGalleries,
          exactConfigurationId,
        })
      : null;

  const gallerySlides = resolvePdpGalleryMedia({
    productImages: detailProduct.images,
    variants: detailProduct.variants,
    matchedConfigurationId: exactConfigurationId,
    mediaPreviewConfigurationId,
    videos: detailProduct.videos,
  });

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
      slug: detailProduct.slug,
      journey,
      storeSlug,
    });

    if (authStatus !== 'authenticated') {
      router.push(buildLoginHref(returnHref));
      return;
    }

    try {
      await addToCartMutation.mutateAsync({
        productId: detailProduct.id,
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
      <ProductImageGallery slides={gallerySlides} />

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

        {wishlist.enabled ? (
          <SecondaryButton
            label={
              wishlist.pending
                ? 'Updating…'
                : wishlist.inWishlist
                  ? 'Saved to wishlist'
                  : 'Add to wishlist'
            }
            onPress={() => void wishlist.toggle()}
            disabled={wishlist.pending}
            style={styles.wishlistBtn}
          />
        ) : null}

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

        <ProductInfoSections
          product={detailProduct}
          reviews={reviewsQuery.data ?? []}
          reviewsLoading={reviewsQuery.isLoading}
          reviewsError={reviewsQuery.isError}
          onRetryReviews={() => void reviewsQuery.refetch()}
        />
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
  wishlistBtn: {
    marginTop: spacing.sm,
    alignSelf: 'stretch',
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
