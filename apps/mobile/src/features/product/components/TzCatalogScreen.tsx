import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { CatalogProductCardView } from './CatalogProductCardView';
import { CategoryChips } from './CategoryChips';
import {
  CatalogEmptyState,
  CatalogErrorState,
  CatalogLoadingState,
} from './CatalogStateViews';
import {
  flattenCatalogProductPages,
  useTzCategories,
  useTzProductsInfinite,
  useTzStores,
} from '../hooks/useCatalogQueries';
import { useCatalogUiStore } from '../state/catalogUiStore';

export function TzCatalogScreen() {
  const selectedTzStoreSlug = useCatalogUiStore((s) => s.selectedTzStoreSlug);
  const setSelectedTzStoreSlug = useCatalogUiStore((s) => s.setSelectedTzStoreSlug);
  const [category, setCategory] = useState<string | null>(null);
  const storesQuery = useTzStores();
  const categoriesQuery = useTzCategories(selectedTzStoreSlug);
  const productsQuery = useTzProductsInfinite({
    storeSlug: selectedTzStoreSlug,
    category,
    perPage: 24,
  });

  const products = useMemo(
    () => flattenCatalogProductPages(productsQuery.data?.pages),
    [productsQuery.data?.pages],
  );

  // Validate persisted store / adopt first Browse store when empty.
  useEffect(() => {
    const stores = storesQuery.data;
    if (!stores) return;

    if (selectedTzStoreSlug) {
      const stillValid = stores.some((store) => store.slug === selectedTzStoreSlug);
      if (!stillValid) {
        setSelectedTzStoreSlug(null);
      }
      return;
    }

    const first = stores[0]?.slug;
    if (first) {
      setSelectedTzStoreSlug(first);
    }
  }, [selectedTzStoreSlug, setSelectedTzStoreSlug, storesQuery.data]);

  if (storesQuery.isLoading && !storesQuery.data) {
    return <CatalogLoadingState label="Loading TZ stores…" />;
  }

  if (storesQuery.isError) {
    return (
      <CatalogErrorState
        error={storesQuery.error}
        onRetry={() => void storesQuery.refetch()}
      />
    );
  }

  const stores = storesQuery.data ?? [];

  if (stores.length === 0) {
    return (
      <CatalogEmptyState
        title="No TZ stores"
        message="No active local TZ stores are available."
      />
    );
  }

  if (!selectedTzStoreSlug) {
    return <CatalogLoadingState label="Selecting store…" />;
  }

  if (productsQuery.isLoading && !productsQuery.data) {
    return <CatalogLoadingState label="Loading store products…" />;
  }

  if (productsQuery.isError && !productsQuery.data) {
    return (
      <CatalogErrorState
        error={productsQuery.error}
        onRetry={() => void productsQuery.refetch()}
      />
    );
  }

  return (
    <FlatList
      style={styles.screen}
      contentContainerStyle={styles.content}
      data={products}
      keyExtractor={(item) => item.id}
      numColumns={2}
      columnWrapperStyle={styles.row}
      refreshControl={
        <RefreshControl
          refreshing={
            (productsQuery.isRefetching && !productsQuery.isFetchingNextPage) ||
            storesQuery.isRefetching
          }
          onRefresh={() => {
            void storesQuery.refetch();
            void categoriesQuery.refetch();
            void productsQuery.refetch();
          }}
        />
      }
      ListHeaderComponent={
        <View>
          <Text style={styles.heading}>Buy from TZ</Text>
          <Text style={styles.subheading}>Local TZ store catalog</Text>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.storeRow}
          >
            {stores.map((store) => {
              const active = store.slug === selectedTzStoreSlug;
              return (
                <Pressable
                  key={store.id}
                  style={[styles.storeChip, active ? styles.storeChipActive : null]}
                  onPress={() => {
                    setSelectedTzStoreSlug(store.slug);
                    setCategory(null);
                  }}
                >
                  <Text
                    style={[styles.storeText, active ? styles.storeTextActive : null]}
                  >
                    {store.name}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>

          <CategoryChips
            categories={categoriesQuery.data ?? []}
            selectedSlug={category}
            onSelect={setCategory}
          />
        </View>
      }
      ListEmptyComponent={
        <CatalogEmptyState
          title="No products"
          message="No products in this store/category."
        />
      }
      renderItem={({ item }) => (
        <View style={styles.cardWrap}>
          <CatalogProductCardView
            product={item}
            journey="TZ_LOCAL"
            storeSlug={selectedTzStoreSlug}
          />
        </View>
      )}
      onEndReached={() => {
        if (productsQuery.hasNextPage && !productsQuery.isFetchingNextPage) {
          void productsQuery.fetchNextPage();
        }
      }}
      onEndReachedThreshold={0.4}
      ListFooterComponent={
        productsQuery.isFetchingNextPage ? (
          <ActivityIndicator style={styles.footer} color="#0a7ea4" />
        ) : !productsQuery.hasNextPage && products.length > 0 ? (
          <Text style={styles.end}>You have reached the end</Text>
        ) : productsQuery.isFetchNextPageError ? (
          <Text style={styles.footerError}>
            Could not load more. Scroll to retry.
          </Text>
        ) : null
      }
    />
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { paddingTop: 16, paddingBottom: 40, paddingHorizontal: 16 },
  heading: {
    fontSize: 22,
    fontWeight: '700',
  },
  subheading: {
    fontSize: 12,
    color: '#666',
    marginBottom: 12,
  },
  storeRow: {
    paddingBottom: 12,
    gap: 8,
  },
  storeChip: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ccc',
    backgroundColor: '#fff',
    marginRight: 8,
  },
  storeChipActive: {
    borderColor: '#0a7ea4',
    backgroundColor: '#e7f5fa',
  },
  storeText: {
    fontSize: 13,
    color: '#444',
  },
  storeTextActive: {
    color: '#0a7ea4',
    fontWeight: '700',
  },
  row: {
    justifyContent: 'space-between',
  },
  cardWrap: {
    width: '48%',
  },
  footer: { marginVertical: 16 },
  end: {
    textAlign: 'center',
    color: '#888',
    marginVertical: 16,
    fontSize: 13,
  },
  footerError: {
    textAlign: 'center',
    color: '#b00020',
    marginVertical: 16,
    fontSize: 13,
  },
});
