import { StyleSheet, Text, View } from 'react-native';
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
  block: { marginTop: 16 },
  title: { fontSize: 15, fontWeight: '700', color: '#111', marginBottom: 8 },
  current: { fontSize: 14, color: '#0a7ea4', marginBottom: 10, fontWeight: '600' },
  event: { flexDirection: 'row', gap: 10, marginBottom: 10 },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    marginTop: 4,
  },
  dotDone: { backgroundColor: '#1b7f3a' },
  dotPending: { backgroundColor: '#cbd5e1' },
  eventBody: { flex: 1 },
  eventLabel: { fontSize: 14, color: '#222', fontWeight: '600' },
  eventMeta: { marginTop: 2, fontSize: 12, color: '#666' },
});
