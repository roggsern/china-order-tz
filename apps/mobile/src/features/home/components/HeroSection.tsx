import { Image } from 'expo-image';
import { StyleSheet, Text, View } from 'react-native';
import type { HomepageHeroSlide } from '../models/types';
import { SectionHeader } from './SectionHeader';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  slides: HomepageHeroSlide[];
};

/** MVP hero — shows primary slide content from CMS (no hardcoded marketing). */
export function HeroSection({ title, subtitle, slides }: Props) {
  const slide = slides[0];
  const imageUrl = slide?.mobile_media?.url ?? slide?.desktop_media?.url ?? null;

  return (
    <View style={styles.section}>
      <SectionHeader title={title || 'Hero'} subtitle={subtitle} />
      <View style={styles.hero}>
        {imageUrl ? (
          <Image source={{ uri: imageUrl }} style={styles.image} contentFit="cover" />
        ) : (
          <View style={[styles.image, styles.placeholder]}>
            <Text style={styles.placeholderText}>Hero</Text>
          </View>
        )}
        <View style={styles.copy}>
          {slide?.eyebrow_text ? (
            <Text style={styles.eyebrow}>{slide.eyebrow_text}</Text>
          ) : null}
          <Text style={styles.headline}>{slide?.headline || 'Welcome'}</Text>
          {slide?.subheadline ? (
            <Text style={styles.subheadline}>{slide.subheadline}</Text>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: 24,
  },
  hero: {
    marginHorizontal: 16,
    borderRadius: 12,
    overflow: 'hidden',
    backgroundColor: '#e8eef2',
  },
  image: {
    width: '100%',
    height: 180,
  },
  placeholder: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#d7e3ea',
  },
  placeholderText: {
    color: '#668',
    fontSize: 14,
  },
  copy: {
    padding: 16,
  },
  eyebrow: {
    fontSize: 12,
    color: '#0a7ea4',
    marginBottom: 4,
    fontWeight: '600',
  },
  headline: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111',
  },
  subheadline: {
    marginTop: 6,
    fontSize: 14,
    color: '#444',
  },
});
