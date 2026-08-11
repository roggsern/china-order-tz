import { useCallback, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { clearSessionOnAuthFailure, useAuthStore } from '@/src/core/auth';
import {
  buildLoginHref,
  getCartErrorMessage,
  isCartUnauthenticatedError,
  useAddToCartMutation,
} from '@/src/features/cart';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { normalizeCustomerPlainText } from '@/src/shared/utils/normalizeCustomerPlainText';
import type { CommerceJourney } from '@/src/shared/types/commerce';
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

  const productId = detailProduct.id;
  const productSlug = detailProduct.slug;
  const matchedConfigurationId = configuration?.matchedConfigurationId ?? null;

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
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <ProductImageGallery images={product.images} />
      <View style={styles.body}>
        <Text style={styles.journey}>
          {journey === 'TZ_LOCAL' ? 'Buy from TZ' : 'Order from China'}
        </Text>
        <Text style={styles.name}>{product.name}</Text>
        {product.commerceSourceLabel ? (
          <Text style={styles.source}>{product.commerceSourceLabel}</Text>
        ) : null}
        <Text style={styles.price}>
          {displayedPrice.source === 'pending'
            ? 'Checking price…'
            : displayedPrice.amount != null
              ? formatCustomerMoney(
                  displayedPrice.amount,
                  displayedPrice.currency ?? 'TZS',
                )
              : '—'}
        </Text>
        {detailProduct.compareAtPrice != null &&
        displayedPrice.source === 'base' ? (
          <Text style={styles.compare}>
            Compare at:{' '}
            {formatCustomerMoney(detailProduct.compareAtPrice, 'TZS')}
          </Text>
        ) : null}

        <ProductAvailabilityBadge
          product={detailProduct}
          configuration={configuration}
        />

        {detailProduct.description ? (
          <Text style={styles.description}>
            {normalizeCustomerPlainText(detailProduct.description)}
          </Text>
        ) : null}

        {detailProduct.shippingPrices &&
        (detailProduct.shippingPrices.air != null ||
          detailProduct.shippingPrices.sea != null) ? (
          <View style={styles.shipping}>
            <Text style={styles.sectionTitle}>Shipping options</Text>
            {detailProduct.shippingPrices.air != null ? (
              <Text style={styles.meta}>
                Air: {formatCustomerMoney(detailProduct.shippingPrices.air, 'TZS')}
              </Text>
            ) : null}
            {detailProduct.shippingPrices.sea != null ? (
              <Text style={styles.meta}>
                Sea: {formatCustomerMoney(detailProduct.shippingPrices.sea, 'TZS')}
              </Text>
            ) : null}
          </View>
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
          <ProductVariantsList variants={product.variants} />
        ) : null}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { paddingBottom: 40 },
  body: { padding: 16 },
  journey: {
    fontSize: 11,
    color: '#0a7ea4',
    fontWeight: '700',
    marginBottom: 6,
  },
  name: {
    fontSize: 22,
    fontWeight: '700',
    color: '#111',
  },
  source: {
    marginTop: 4,
    fontSize: 13,
    color: '#666',
  },
  price: {
    marginTop: 12,
    fontSize: 20,
    fontWeight: '700',
    color: '#0a7ea4',
  },
  compare: {
    marginTop: 4,
    fontSize: 13,
    color: '#888',
    textDecorationLine: 'line-through',
  },
  description: {
    marginTop: 16,
    fontSize: 14,
    lineHeight: 20,
    color: '#333',
  },
  shipping: {
    marginTop: 16,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '700',
    marginBottom: 6,
  },
  meta: {
    fontSize: 13,
    color: '#555',
    marginTop: 2,
  },
  successText: {
    marginTop: 12,
    fontSize: 14,
    color: '#1b7f3a',
    fontWeight: '600',
  },
  errorText: {
    marginTop: 12,
    fontSize: 14,
    color: '#b00020',
  },
});
