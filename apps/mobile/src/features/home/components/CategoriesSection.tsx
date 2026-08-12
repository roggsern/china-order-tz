import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useJourneyStore } from '@/src/core/auth';
import { useCatalogUiStore } from '@/src/features/product';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { HomepageCategoryCard } from '../models/types';
import { resolveCategoryPresentation } from '../utils/categoryPresentation';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  categories: HomepageCategoryCard[];
};

/**
 * Category / collection discovery.
 * Deep-links China Shop (browse route) with the selected category slug via catalogUiStore.
 * Visual priority: server image when present → owned presentation artwork → generic artwork.
 */
export function CategoriesSection({ title, subtitle, categories }: Props) {
  const setJourney = useJourneyStore((s) => s.setJourney);
  const setSelectedChinaCategorySlug = useCatalogUiStore(
    (s) => s.setSelectedChinaCategorySlug,
  );

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
        {categories.map((category) => {
          const presentation = resolveCategoryPresentation({
            slug: category.slug,
            name: category.name,
          });
          return (
            <Pressable
              key={category.id}
              style={styles.tile}
              onPress={() => {
                setJourney('CHINA_IMPORT');
                setSelectedChinaCategorySlug(category.slug);
                router.push('/(app)/(tabs)/browse');
              }}
              accessibilityRole="button"
              accessibilityLabel={category.name}
            >
              <View style={styles.imageFrame}>
                {category.imageUrl ? (
                  <Image
                    source={{ uri: category.imageUrl }}
                    style={styles.image}
                    contentFit="cover"
                  />
                ) : (
                  <Image
                    source={presentation.artwork}
                    style={styles.image}
                    contentFit="cover"
                  />
                )}
              </View>
              <Text style={styles.name} numberOfLines={2}>
                {category.name}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: spacing.xxl },
  rail: { paddingHorizontal: spacing.lg },
  tile: { width: 112, marginRight: spacing.md },
  imageFrame: {
    width: 112,
    height: 112,
    borderRadius: radius.xl,
    overflow: 'hidden',
    backgroundColor: colors.surfaceCream,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
    marginBottom: spacing.sm,
  },
  image: {
    width: '100%',
    height: '100%',
  },
  name: {
    ...typography.label,
    color: colors.text,
    textAlign: 'center',
  },
});
