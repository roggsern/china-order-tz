import { StyleSheet, Text, View } from 'react-native';
import type { CatalogProductDetail, ProductConfiguration } from '../models/types';
import { resolveCustomerAvailabilityLabel } from '../utils/resolveCustomerAvailabilityLabel';

type Props = {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
};

/** Customer availability from server fields — no conflicting technical flags. */
export function ProductAvailabilityBadge({ product, configuration }: Props) {
  const label = resolveCustomerAvailabilityLabel({ product, configuration });

  return (
    <View style={styles.wrap}>
      <Text style={styles.status}>{label}</Text>
      {product.unavailabilityReason ? (
        <Text style={styles.reason}>{product.unavailabilityReason}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 12,
    padding: 12,
    borderRadius: 8,
    backgroundColor: '#f5f7f8',
  },
  status: {
    fontSize: 14,
    fontWeight: '600',
    color: '#222',
  },
  reason: {
    marginTop: 6,
    fontSize: 13,
    color: '#b00020',
  },
});
