import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { HomepageCategoryCard } from '../models/types';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  categories: HomepageCategoryCard[];
};

export function CategoriesSection({ title, subtitle, categories }: Props) {
  const setJourney = useJourneyStore((s) => s.setJourney);

  if (categories.length === 0) return null;

  return (
    <View style={styles.section}>
      <SectionHeader
        title={title?.trim() || 'Shop by category'}
        subtitle={subtitle}
      />
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {categories.map((category) => (
          <Pressable
            key={category.id}
            style={styles.tile}
            onPress={() => {
              setJourney('CHINA_IMPORT');
              router.push('/(app)/(tabs)/browse');
            }}
          >
            {category.imageUrl ? (
              <Image
                source={{ uri: category.imageUrl }}
                style={styles.image}
                contentFit="cover"
              />
            ) : (
              <View style={[styles.image, styles.fallback]}>
                <Text style={styles.fallbackText}>
                  {category.name.slice(0, 1).toUpperCase()}
                </Text>
              </View>
            )}
            <Text style={styles.name} numberOfLines={2}>
              {category.name}
            </Text>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: spacing.xxl },
  rail: { paddingHorizontal: spacing.lg },
  tile: { width: 104, marginRight: spacing.md },
  image: {
    width: 104,
    height: 104,
    borderRadius: radius.xl,
    backgroundColor: colors.backgroundMuted,
    marginBottom: spacing.sm,
  },
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primaryMuted,
  },
  fallbackText: {
    ...typography.heading,
    color: colors.primaryPressed,
  },
  name: {
    ...typography.label,
    color: colors.text,
    textAlign: 'center',
  },
});
