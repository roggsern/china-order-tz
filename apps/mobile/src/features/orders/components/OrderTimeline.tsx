import { StyleSheet, Text, View } from 'react-native';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { OrderProgress, OrderTimelineEvent } from '../models/types';

type Props = {
  title?: string;
  progress?: OrderProgress | null;
  events?: OrderTimelineEvent[];
};

export function OrderTimeline({
  title = 'Progress',
  progress,
  events,
}: Props) {
  const timeline =
    events && events.length > 0
      ? events
      : (progress?.steps ?? []).map((step) => ({
          key: step.key,
          label: step.label,
          description: null,
          completed: step.completed,
          completedAt: null,
          step: step.key,
        }));

  if (timeline.length === 0 && !progress?.currentLabel) {
    return null;
  }

  return (
    <View style={styles.block}>
      <Text style={styles.title}>{title}</Text>
      {progress?.currentLabel ? (
        <Text style={styles.current}>Current: {progress.currentLabel}</Text>
      ) : null}
      {timeline.map((event, index) => (
        <View
          key={`${event.key ?? event.label ?? index}-${index}`}
          style={styles.event}
        >
          <View
            style={[
              styles.dot,
              event.completed ? styles.dotDone : styles.dotPending,
            ]}
          />
          <View style={styles.eventBody}>
            <Text style={styles.eventLabel}>
              {event.label ?? event.description ?? event.step ?? 'Update'}
            </Text>
            {event.description && event.label !== event.description ? (
              <Text style={styles.eventMeta}>{event.description}</Text>
            ) : null}
            {event.completedAt ? (
              <Text style={styles.eventMeta}>{event.completedAt}</Text>
            ) : null}
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  block: { marginTop: spacing.lg },
  title: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  current: {
    ...typography.bodyStrong,
    color: colors.primaryPressed,
    marginBottom: spacing.md,
  },
  event: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    marginTop: spacing.xs,
  },
  dotDone: { backgroundColor: colors.success },
  dotPending: { backgroundColor: colors.borderStrong },
  eventBody: { flex: 1 },
  eventLabel: { ...typography.bodyStrong },
  eventMeta: { marginTop: spacing.xxs, ...typography.caption },
});
