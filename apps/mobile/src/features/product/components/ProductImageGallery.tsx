import { Image } from 'expo-image';
import { ScrollView, StyleSheet, View } from 'react-native';
import type { CatalogImage } from '../models/types';

type Props = {
  images: CatalogImage[];
};

export function ProductImageGallery({ images }: Props) {
  if (images.length === 0) {
    return <View style={[styles.image, styles.placeholder]} />;
  }

  return (
    <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
      {images.map((image, index) => (
        <Image
          key={image.id ?? `${image.url}-${index}`}
          source={{ uri: image.url ?? undefined }}
          style={styles.image}
          contentFit="cover"
        />
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  image: {
    width: 360,
    maxWidth: '100%',
    height: 280,
    backgroundColor: '#eee',
  },
  placeholder: {
    width: '100%',
    alignSelf: 'stretch',
  },
});
