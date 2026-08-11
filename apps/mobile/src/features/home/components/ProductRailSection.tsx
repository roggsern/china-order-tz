import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { HomepageProductCard } from '../models/types';
import { ProductCard } from './ProductCard';

type Props = {
  title: string;
  subtitle?: string | null;
  products: HomepageProductCard[];
  emptyLabel?: string;
  badgeLabel?: string;
};

/** Reusable horizontal product rail for featured / new / best-seller sections. */
export function ProductRailSection({
  title,
  subtitle,
  products,
  emptyLabel = 'No products in this section yet.',
  badgeLabel,
}: Props) {
  return (
    <View style={styles.section}>
      <SectionHeader title={title} subtitle={subtitle} />
      {products.length === 0 ? (
        <Text style={styles.empty}>{emptyLabel}</Text>
      ) : (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.rail}
        >
          {products.map((product) => (
            <ProductCard
              key={product.id}
              product={product}
              badgeLabel={badgeLabel}
            />
          ))}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: spacing.xxl,
  },
  rail: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xs,
  },
  empty: {
    paddingHorizontal: spacing.lg,
    ...typography.caption,
    color: colors.textSubtle,
  },
});
