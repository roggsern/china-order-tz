import { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
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
  useChinaCategories,
  useChinaProductsInfinite,
} from '../hooks/useCatalogQueries';

export function ChinaCatalogScreen() {
  const [category, setCategory] = useState<string | null>(null);
  const categoriesQuery = useChinaCategories();
  const productsQuery = useChinaProductsInfinite({ category, perPage: 24 });

  const products = useMemo(
    () => flattenCatalogProductPages(productsQuery.data?.pages),
    [productsQuery.data?.pages],
  );

  if (productsQuery.isLoading && !productsQuery.data) {
    return <CatalogLoadingState label="Loading China products…" />;
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
            productsQuery.isRefetching && !productsQuery.isFetchingNextPage
          }
          onRefresh={() => {
            void categoriesQuery.refetch();
            void productsQuery.refetch();
          }}
        />
      }
      ListHeaderComponent={
        <View>
          <Text style={styles.heading}>Order from China</Text>
          <Text style={styles.subheading}>China import catalog</Text>
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
          message="No China products match this filter."
        />
      }
      renderItem={({ item }) => (
        <View style={styles.cardWrap}>
          <CatalogProductCardView product={item} journey="CHINA_IMPORT" />
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
