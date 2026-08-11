import { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { colors, spacing, typography } from '@/src/shared/theme';
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
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
      ListHeaderComponent={
        <View style={styles.header}>
          <Text style={styles.eyebrow}>Catalog</Text>
          <Text style={styles.heading}>Order from China</Text>
          <Text style={styles.subheading}>
            Factory-direct import products
          </Text>
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
          <ActivityIndicator style={styles.footer} color={colors.primary} />
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
  screen: { flex: 1, backgroundColor: colors.background },
  content: {
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
    paddingHorizontal: spacing.lg,
  },
  header: {
    marginBottom: spacing.sm,
  },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: {
    ...typography.heading,
  },
  subheading: {
    ...typography.caption,
    marginBottom: spacing.md,
  },
  row: {
    justifyContent: 'space-between',
  },
  cardWrap: {
    width: '48%',
  },
  footer: { marginVertical: spacing.lg },
  end: {
    ...typography.caption,
    textAlign: 'center',
    marginVertical: spacing.lg,
  },
  footerError: {
    ...typography.caption,
    color: colors.error,
    textAlign: 'center',
    marginVertical: spacing.lg,
  },
});
