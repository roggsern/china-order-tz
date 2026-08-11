import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import { useCatalogUiStore } from '@/src/features/product';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { HomepageStoreCard } from '../models/types';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  stores: HomepageStoreCard[];
};

export function StoresSection({ title, subtitle, stores }: Props) {
  const setJourney = useJourneyStore((s) => s.setJourney);
  const setSelectedTzStoreSlug = useCatalogUiStore((s) => s.setSelectedTzStoreSlug);

  if (stores.length === 0) return null;

  return (
    <View style={styles.section}>
      <SectionHeader
        title={title?.trim() || 'Popular stores'}
        subtitle={subtitle}
      />
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {stores.map((store) => (
          <Pressable
            key={store.id}
            style={styles.tile}
            onPress={() => {
              setJourney('TZ_LOCAL');
              setSelectedTzStoreSlug(store.slug);
              router.push('/(app)/(tabs)/browse');
            }}
          >
            {store.imageUrl ? (
              <Image
                source={{ uri: store.imageUrl }}
                style={styles.image}
                contentFit="cover"
              />
            ) : (
              <View style={[styles.image, styles.fallback]}>
                <Text style={styles.fallbackText}>
                  {store.name.slice(0, 1).toUpperCase()}
                </Text>
              </View>
            )}
            <Text style={styles.name} numberOfLines={2}>
              {store.name}
            </Text>
            {store.description ? (
              <Text style={styles.desc} numberOfLines={2}>
                {store.description}
              </Text>
            ) : null}
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: spacing.xxl },
  rail: { paddingHorizontal: spacing.lg },
  tile: {
    width: 148,
    marginRight: spacing.md,
    padding: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  image: {
    width: 56,
    height: 56,
    borderRadius: radius.lg,
    backgroundColor: colors.backgroundMuted,
    marginBottom: spacing.sm,
  },
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primaryMuted,
  },
  fallbackText: {
    ...typography.title,
    color: colors.primaryPressed,
  },
  name: {
    ...typography.label,
    color: colors.text,
    marginBottom: spacing.xxs,
  },
  desc: {
    ...typography.caption,
  },
});
