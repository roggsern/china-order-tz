import { Image } from 'expo-image';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { PriceText } from '@/src/shared/ui/PriceText';
import { listImageProps } from '@/src/shared/media/listImageProps';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { CartItem } from '../models/types';

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

  const journeyTone =
    item.commerceChannelCode === 'TZ_LOCAL' ? 'success' : 'brand';

  return (
    <Card elevated style={styles.card}>
      <View style={styles.row}>
        {item.imageUrl ? (
          <Image
            source={{ uri: item.imageUrl }}
            style={styles.image}
            contentFit="cover"
            transition={200}
            {...listImageProps(item.imageUrl)}
          />
        ) : (
          <View style={[styles.image, styles.placeholder]}>
            <Text style={styles.placeholderText}>No image</Text>
          </View>
        )}

        <View style={styles.body}>
          <View style={styles.badgeRow}>
            <Badge label={item.journeyLabel} tone={journeyTone} />
            {item.commerceSourceLabel ? (
              <Badge label={item.commerceSourceLabel} tone="neutral" />
            ) : null}
          </View>

          <Text style={styles.name} numberOfLines={2}>
            {item.productName}
          </Text>
          {variantDetails ? (
            <Text style={styles.variant} numberOfLines={2}>
              {variantDetails}
            </Text>
          ) : null}

          <View style={styles.priceRow}>
            <PriceText
              value={item.unitPrice}
              currency={item.currency}
              style={styles.unitPrice}
              accessibilityLabelPrefix="Unit price"
            />
            <Text style={styles.each}>each</Text>
          </View>
          <PriceText
            value={item.lineSubtotal}
            currency={item.currency}
            accessibilityLabelPrefix="Line total"
          />

          <View style={styles.actions}>
            <View style={styles.qty}>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Decrease quantity"
                style={[
                  styles.qtyButton,
                  busy || item.quantity <= 1 ? styles.disabled : null,
                ]}
                disabled={busy || item.quantity <= 1}
                onPress={onDecrease}
              >
                <Text style={styles.qtyButtonText}>−</Text>
              </Pressable>
              <Text style={styles.qtyValue}>{item.quantity}</Text>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Increase quantity"
                style={[styles.qtyButton, busy ? styles.disabled : null]}
                disabled={busy}
                onPress={onIncrease}
              >
                <Text style={styles.qtyButtonText}>+</Text>
              </Pressable>
            </View>

            <Pressable
              accessibilityRole="button"
              style={[styles.remove, busy ? styles.disabled : null]}
              disabled={busy}
              onPress={onRemove}
            >
              {busy ? (
                <ActivityIndicator size="small" color={colors.error} />
              ) : (
                <Text style={styles.removeText}>Remove</Text>
              )}
            </Pressable>
          </View>
        </View>
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    marginBottom: spacing.md,
    padding: spacing.md,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  image: {
    width: 96,
    height: 96,
    borderRadius: radius.xl,
    backgroundColor: colors.backgroundMuted,
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    ...typography.caption,
  },
  body: {
    flex: 1,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.xs,
  },
  name: {
    ...typography.bodyStrong,
  },
  variant: {
    marginTop: spacing.xxs,
    ...typography.caption,
  },
  priceRow: {
    marginTop: spacing.sm,
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: spacing.xs,
  },
  unitPrice: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  each: {
    ...typography.caption,
  },
  actions: {
    marginTop: spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  qty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  qtyButton: {
    width: 36,
    height: 36,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
  },
  qtyButtonText: {
    ...typography.title,
    fontSize: 18,
  },
  qtyValue: {
    minWidth: 28,
    textAlign: 'center',
    ...typography.bodyStrong,
  },
  remove: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
  },
  removeText: {
    ...typography.label,
    color: colors.error,
    fontWeight: '700',
  },
  disabled: {
    opacity: 0.4,
  },
});
