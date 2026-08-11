import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { brandColors, colors, radius, spacing, typography } from '@/src/shared/theme';

const OPTIONS: {
  value: CommerceJourney;
  label: string;
  hint: string;
  accent: string;
}[] = [
  {
    value: 'CHINA_IMPORT',
    label: 'Order from China',
    hint: 'Factory-direct import',
    accent: brandColors.chinaRed,
  },
  {
    value: 'TZ_LOCAL',
    label: 'Buy from Tanzania',
    hint: 'Local trusted stores',
    accent: brandColors.tzGreen,
  },
];

export function JourneySwitcher() {
  const journey = useJourneyStore((s) => s.journey);
  const setJourney = useJourneyStore((s) => s.setJourney);

  return (
    <View style={styles.wrap}>
      <Text style={styles.eyebrow}>Shopping journey</Text>
      <View style={styles.row}>
        {OPTIONS.map((option) => {
          const active = journey === option.value;
          return (
            <Pressable
              key={option.value}
              accessibilityRole="button"
              accessibilityState={{ selected: active }}
              style={[
                styles.card,
                active ? styles.cardActive : null,
                active ? { borderColor: option.accent } : null,
              ]}
              onPress={() => setJourney(option.value)}
            >
              <View style={[styles.dot, { backgroundColor: option.accent }]} />
              <Text
                style={[styles.label, active ? styles.labelActive : null]}
                numberOfLines={2}
              >
                {option.label}
              </Text>
              <Text style={styles.hint} numberOfLines={1}>
                {option.hint}
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
    marginBottom: spacing.xxl,
  },
  eyebrow: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    color: colors.primaryPressed,
    marginBottom: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  card: {
    flex: 1,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1.5,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    minHeight: 88,
  },
  cardActive: {
    backgroundColor: colors.primaryMuted,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginBottom: spacing.sm,
  },
  label: {
    ...typography.label,
    color: colors.textSecondary,
    fontSize: 13,
    marginBottom: spacing.xxs,
  },
  labelActive: {
    color: colors.text,
    fontWeight: '700',
  },
  hint: {
    ...typography.caption,
    color: colors.textSubtle,
  },
});
