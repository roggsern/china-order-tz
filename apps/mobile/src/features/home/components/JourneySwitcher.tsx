import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useJourneyStore } from '@/src/core/auth';
import type { CommerceJourney } from '@/src/shared/types/commerce';

const OPTIONS: { value: CommerceJourney; label: string }[] = [
  { value: 'CHINA_IMPORT', label: 'Order from China' },
  { value: 'TZ_LOCAL', label: 'Buy from TZ' },
];

export function JourneySwitcher() {
  const journey = useJourneyStore((s) => s.journey);
  const setJourney = useJourneyStore((s) => s.setJourney);

  return (
    <View style={styles.wrap}>
      {OPTIONS.map((option) => {
        const active = journey === option.value;
        return (
          <Pressable
            key={option.value}
            style={[styles.chip, active ? styles.chipActive : null]}
            onPress={() => setJourney(option.value)}
          >
            <Text style={[styles.chipText, active ? styles.chipTextActive : null]}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  chip: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ccc',
    backgroundColor: '#fff',
    alignItems: 'center',
  },
  chipActive: {
    borderColor: '#0a7ea4',
    backgroundColor: '#e7f5fa',
  },
  chipText: {
    fontSize: 13,
    color: '#444',
    fontWeight: '500',
    textAlign: 'center',
  },
  chipTextActive: {
    color: '#0a7ea4',
    fontWeight: '700',
  },
});
