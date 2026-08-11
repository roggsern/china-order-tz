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
import { colors, radius, spacing, typography } from '@/src/shared/theme';
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
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
      ListHeaderComponent={
        <View style={styles.header}>
          <Text style={styles.eyebrow}>Catalog</Text>
          <Text style={styles.heading}>Buy from Tanzania</Text>
          <Text style={styles.subheading}>Local trusted store products</Text>

          <Text style={styles.chipLabel}>Stores</Text>
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
                  accessibilityRole="button"
                  accessibilityState={{ selected: active }}
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

          <Text style={styles.chipLabel}>Categories</Text>
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
  chipLabel: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.textMuted,
    marginBottom: spacing.sm,
  },
  storeRow: {
    paddingBottom: spacing.md,
    gap: spacing.sm,
  },
  storeChip: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm + 2,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    marginRight: spacing.sm,
  },
  storeChipActive: {
    borderColor: colors.journeyTz,
    backgroundColor: colors.successMuted,
  },
  storeText: {
    ...typography.label,
    color: colors.textSecondary,
  },
  storeTextActive: {
    color: colors.journeyTz,
    fontWeight: '700',
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
