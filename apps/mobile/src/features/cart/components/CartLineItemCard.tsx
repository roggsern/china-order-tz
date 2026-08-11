import { Image } from 'expo-image';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import type { CartItem } from '../models/types';
import { formatCartMoney } from '../utils/mapCart';

type Props = {
  item: CartItem;
  busy?: boolean;
  onIncrease: () => void;
  onDecrease: () => void;
  onRemove: () => void;
};

export function CartLineItemCard({
  item,
  busy,
  onIncrease,
  onDecrease,
  onRemove,
}: Props) {
  const variantDetails =
    item.displayAttributes.length > 0
      ? item.displayAttributes
          .map((row) => `${row.attribute}: ${row.value}`)
          .join(' · ')
      : [item.variantName, item.variantSku].filter(Boolean).join(' · ');

  return (
    <View style={styles.card}>
      {item.imageUrl ? (
        <Image source={{ uri: item.imageUrl }} style={styles.image} contentFit="cover" />
      ) : (
        <View style={[styles.image, styles.placeholder]}>
          <Text style={styles.placeholderText}>No image</Text>
        </View>
      )}

      <View style={styles.body}>
        <Text style={styles.journey}>{item.journeyLabel}</Text>
        <Text style={styles.name} numberOfLines={2}>
          {item.productName}
        </Text>
        {item.commerceSourceLabel ? (
          <Text style={styles.source}>{item.commerceSourceLabel}</Text>
        ) : null}
        {variantDetails ? (
          <Text style={styles.variant} numberOfLines={2}>
            {variantDetails}
          </Text>
        ) : null}

        <Text style={styles.price}>
          {formatCartMoney(item.unitPrice, item.currency)} each
        </Text>
        <Text style={styles.lineTotal}>
          Line: {formatCartMoney(item.lineSubtotal, item.currency)}
        </Text>

        <View style={styles.actions}>
          <View style={styles.qty}>
            <Pressable
              style={[styles.qtyButton, busy || item.quantity <= 1 ? styles.disabled : null]}
              disabled={busy || item.quantity <= 1}
              onPress={onDecrease}
            >
              <Text style={styles.qtyButtonText}>−</Text>
            </Pressable>
            <Text style={styles.qtyValue}>{item.quantity}</Text>
            <Pressable
              style={[styles.qtyButton, busy ? styles.disabled : null]}
              disabled={busy}
              onPress={onIncrease}
            >
              <Text style={styles.qtyButtonText}>+</Text>
            </Pressable>
          </View>

          <Pressable
            style={[styles.remove, busy ? styles.disabled : null]}
            disabled={busy}
            onPress={onRemove}
          >
            {busy ? (
              <ActivityIndicator size="small" color="#b00020" />
            ) : (
              <Text style={styles.removeText}>Remove</Text>
            )}
          </Pressable>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    gap: 12,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#ddd',
  },
  image: {
    width: 88,
    height: 88,
    borderRadius: 8,
    backgroundColor: '#eee',
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    fontSize: 10,
    color: '#888',
  },
  body: {
    flex: 1,
  },
  journey: {
    fontSize: 11,
    color: '#0a7ea4',
    fontWeight: '700',
    marginBottom: 2,
  },
  name: {
    fontSize: 15,
    fontWeight: '700',
    color: '#222',
  },
  source: {
    marginTop: 2,
    fontSize: 12,
    color: '#666',
  },
  variant: {
    marginTop: 4,
    fontSize: 12,
    color: '#555',
  },
  price: {
    marginTop: 8,
    fontSize: 13,
    color: '#333',
  },
  lineTotal: {
    marginTop: 2,
    fontSize: 13,
    fontWeight: '600',
    color: '#0a7ea4',
  },
  actions: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  qty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  qtyButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ccc',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  qtyButtonText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#222',
  },
  qtyValue: {
    minWidth: 24,
    textAlign: 'center',
    fontSize: 15,
    fontWeight: '700',
  },
  remove: {
    paddingHorizontal: 8,
    paddingVertical: 6,
  },
  removeText: {
    color: '#b00020',
    fontWeight: '600',
    fontSize: 13,
  },
  disabled: {
    opacity: 0.4,
  },
});
