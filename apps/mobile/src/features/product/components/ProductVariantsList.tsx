import { StyleSheet, Text, View } from 'react-native';
import type { CatalogProductVariant } from '../models/types';

type Props = {
  variants: CatalogProductVariant[];
};

/** Lists API variants/configuration rows with server-provided prices. */
export function ProductVariantsList({ variants }: Props) {
  if (variants.length === 0) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Variants</Text>
      {variants.map((variant) => (
        <View key={variant.id} style={styles.row}>
          <View style={styles.copy}>
            <Text style={styles.name}>{variant.name || variant.sku || variant.id}</Text>
            {variant.displayAttributes?.length ? (
              <Text style={styles.attrs}>
                {variant.displayAttributes
                  .map((attr) => `${attr.attribute}: ${attr.value}`)
                  .join(' · ')}
              </Text>
            ) : null}
            {variant.inStock != null ? (
              <Text style={styles.stock}>
                {variant.inStock ? 'In stock' : 'Out of stock'}
              </Text>
            ) : null}
          </View>
          <Text style={styles.price}>
            {variant.price != null ? String(variant.price) : '—'}
          </Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 20,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 10,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#ddd',
  },
  copy: {
    flex: 1,
  },
  name: {
    fontSize: 14,
    fontWeight: '600',
    color: '#222',
  },
  attrs: {
    marginTop: 2,
    fontSize: 12,
    color: '#666',
  },
  stock: {
    marginTop: 2,
    fontSize: 12,
    color: '#555',
  },
  price: {
    fontSize: 14,
    fontWeight: '600',
    color: '#0a7ea4',
  },
});
