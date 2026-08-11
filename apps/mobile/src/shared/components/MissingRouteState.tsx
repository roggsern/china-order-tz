import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';

type Props = {
  title: string;
  message: string;
  primaryLabel?: string;
  primaryHref?: string;
  secondaryLabel?: string;
  onSecondaryPress?: () => void;
};

/**
 * Customer-facing empty state when a required route id/context is missing.
 */
export function MissingRouteState({
  title,
  message,
  primaryLabel = 'Go to Home',
  primaryHref = '/(app)/(tabs)/home',
  secondaryLabel = 'Back',
  onSecondaryPress,
}: Props) {
  return (
    <View style={styles.centered}>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.body}>{message}</Text>
      <Pressable
        style={styles.primaryButton}
        onPress={() => router.replace(primaryHref as never)}
      >
        <Text style={styles.primaryButtonText}>{primaryLabel}</Text>
      </Pressable>
      <Pressable
        style={styles.secondaryButton}
        onPress={() => {
          if (onSecondaryPress) {
            onSecondaryPress();
            return;
          }
          if (router.canGoBack()) {
            router.back();
          } else {
            router.replace(primaryHref as never);
          }
        }}
      >
        <Text style={styles.secondaryButtonText}>{secondaryLabel}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  title: { fontSize: 18, fontWeight: '700', color: '#222', textAlign: 'center' },
  body: { fontSize: 14, color: '#666', textAlign: 'center', marginBottom: 8 },
  primaryButton: {
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 20,
    minWidth: 180,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '700' },
  secondaryButton: {
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 20,
    minWidth: 180,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#0a7ea4',
  },
  secondaryButtonText: { color: '#0a7ea4', fontWeight: '600' },
});
