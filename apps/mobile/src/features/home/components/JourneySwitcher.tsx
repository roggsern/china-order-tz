import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { brandColors, colors, radius, spacing, typography } from '@/src/shared/theme';

const OPTIONS: {
  value: CommerceJourney;
  label: string;
  accent: string;
}[] = [
  {
    value: 'CHINA_IMPORT',
    label: 'Order from China',
    accent: brandColors.chinaRed,
  },
  {
    value: 'TZ_LOCAL',
    label: 'Buy from Tanzania',
    accent: brandColors.tzGreen,
  },
];

/** Compact journey control — must not dominate the first viewport. */
export function JourneySwitcher() {
  const journey = useJourneyStore((s) => s.journey);
  const setJourney = useJourneyStore((s) => s.setJourney);

  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        {OPTIONS.map((option) => {
          const active = journey === option.value;
          return (
            <Pressable
              key={option.value}
              accessibilityRole="button"
              accessibilityState={{ selected: active }}
              style={[
                styles.chip,
                active ? styles.chipActive : null,
                active ? { borderColor: option.accent } : null,
              ]}
              onPress={() => setJourney(option.value)}
            >
              <View style={[styles.dot, { backgroundColor: option.accent }]} />
              <Text
                style={[styles.label, active ? styles.labelActive : null]}
                numberOfLines={1}
              >
                {option.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  chip: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    borderRadius: radius.full,
    borderWidth: 1.5,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    minHeight: 40,
  },
  chipActive: {
    backgroundColor: colors.primaryMuted,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  label: {
    ...typography.label,
    color: colors.textSecondary,
    fontSize: 12,
    flexShrink: 1,
  },
  labelActive: {
    color: colors.text,
    fontWeight: '700',
  },
});
