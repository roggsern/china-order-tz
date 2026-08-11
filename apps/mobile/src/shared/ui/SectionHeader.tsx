import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { spacing, typography } from '../theme';

type Props = {
  title: string;
  subtitle?: string | null;
  style?: StyleProp<ViewStyle>;
  /** Horizontal inset; default true for list sections. */
  inset?: boolean;
};

/**
 * Shared section title for homepage rails and future catalog blocks.
 * Feature screens may keep local headers until they migrate.
 */
export function SectionHeader({
  title,
  subtitle,
  style,
  inset = true,
}: Props) {
  return (
    <View style={[styles.wrap, inset ? styles.inset : null, style]}>
      <Text style={styles.title}>{title}</Text>
      {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: spacing.md,
  },
  inset: {
    paddingHorizontal: spacing.lg,
  },
  title: {
    ...typography.title,
  },
  subtitle: {
    ...typography.caption,
    marginTop: spacing.xs,
    fontSize: 13,
  },
});
