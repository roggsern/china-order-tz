import { useState } from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { Image } from 'expo-image';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

type Props = {
  imageUrl: string | null | undefined;
  size?: number;
  extraBadge?: number | null;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
};

/** Shared product thumbnail for orders — missing/failed image shows a neutral placeholder. */
export function OrderThumbnail({
  imageUrl,
  size = 72,
  extraBadge,
  style,
  accessibilityLabel,
}: Props) {
  const uri =
    typeof imageUrl === 'string' && imageUrl.trim() !== '' ? imageUrl.trim() : null;
  const [failedUri, setFailedUri] = useState<string | null>(null);
  const showImage = Boolean(uri) && failedUri !== uri;

  return (
    <View
      style={[
        styles.wrap,
        { width: size, height: size, borderRadius: radius.md },
        style,
      ]}
      accessibilityLabel={
        accessibilityLabel ?? (showImage ? 'Product image' : 'No product image')
      }
    >
      {showImage ? (
        <Image
          source={{ uri: uri! }}
          style={styles.image}
          contentFit="cover"
          onError={() => {
            if (uri) setFailedUri(uri);
          }}
        />
      ) : (
        <View style={styles.placeholder}>
          <Text style={styles.placeholderText}>No image</Text>
        </View>
      )}
      {extraBadge != null && extraBadge > 0 ? (
        <View style={styles.badge} accessibilityLabel={`${extraBadge} more items`}>
          <Text style={styles.badgeText}>+{extraBadge}</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    overflow: 'hidden',
    backgroundColor: colors.backgroundMuted,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
  },
  image: { width: '100%', height: '100%' },
  placeholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.xs,
  },
  placeholderText: {
    ...typography.caption,
    fontSize: 10,
    textAlign: 'center',
    color: colors.textMuted,
  },
  badge: {
    position: 'absolute',
    right: spacing.xxs,
    bottom: spacing.xxs,
    backgroundColor: colors.text,
    paddingHorizontal: spacing.xs,
    paddingVertical: 2,
    borderRadius: radius.sm,
  },
  badgeText: {
    ...typography.caption,
    fontSize: 10,
    fontWeight: '700',
    color: colors.onPrimary,
  },
});
