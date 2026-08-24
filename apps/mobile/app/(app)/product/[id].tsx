import { View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import { CatalogEmptyState } from '@/src/features/product/components/CatalogStateViews';
import {
  parseJourneyParam,
  ProductDetailScreen,
} from '@/src/features/product';
import { MissingRouteState } from '@/src/shared/components/MissingRouteState';

/**
 * TZ deep links must include explicit store — never inherit selected/first store.
 */
export default function ProductRoute() {
  const params = useLocalSearchParams<{
    id?: string | string[];
    journey?: string | string[];
    store?: string | string[];
  }>();
  const journeyFromStore = useJourneyStore((s) => s.journey);

  const productKey = Array.isArray(params.id) ? params.id[0] : params.id;
  const journeyParam = Array.isArray(params.journey) ? params.journey[0] : params.journey;
  const storeParam = Array.isArray(params.store) ? params.store[0] : params.store;

  const journey = parseJourneyParam(journeyParam, journeyFromStore);
  const storeSlug =
    typeof storeParam === 'string' && storeParam.trim() !== ''
      ? storeParam.trim()
      : null;

  if (!productKey?.trim()) {
    return (
      <MissingRouteState
        title="Product unavailable"
        message="This product link is missing. Open Shop to find products."
        primaryLabel="Go to Shop"
        primaryHref="/(app)/(tabs)/browse"
      />
    );
  }

  if (journey === 'TZ_LOCAL' && !storeSlug) {
    return (
      <View style={{ flex: 1, backgroundColor: '#fff' }}>
        <CatalogEmptyState
          title="Store unavailable"
          message="This product could not be opened because its store is missing. Choose a store in Shop, then open the product from there."
          actions={[
            {
              label: 'Go to Shop',
              primary: true,
              onPress: () => router.replace('/(app)/(tabs)/browse'),
            },
            {
              label: 'Back',
              onPress: () => {
                if (router.canGoBack()) router.back();
                else router.replace('/(app)/(tabs)/home');
              },
            },
          ]}
        />
      </View>
    );
  }

  return (
    <ProductDetailScreen
      productKey={decodeURIComponent(productKey)}
      journey={journey}
      storeSlug={storeSlug}
    />
  );
}
