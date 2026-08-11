import { ScrollView, StyleSheet, Text, View } from 'react-native';
import type { HomepageProductCard } from '../models/types';
import { ProductCard } from './ProductCard';
import { SectionHeader } from './SectionHeader';

type Props = {
  title: string;
  subtitle?: string | null;
  products: HomepageProductCard[];
  emptyLabel?: string;
};

/** Reusable horizontal product rail for featured / new / best-seller sections. */
export function ProductRailSection({
  title,
  subtitle,
  products,
  emptyLabel = 'No products in this section yet.',
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
            <ProductCard key={product.id} product={product} />
          ))}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: 24,
  },
  rail: {
    paddingHorizontal: 16,
  },
  empty: {
    paddingHorizontal: 16,
    fontSize: 13,
    color: '#888',
  },
});
