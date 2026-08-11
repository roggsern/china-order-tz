import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

type Props = {
  message?: string;
};

/** MVP splash while SecureStore + GET /me bootstrap runs. */
export function SplashView({ message = 'Restoring session…' }: Props) {
  return (
    <View style={styles.container} accessibilityLabel="splash">
      <Text style={styles.title}>CHINA ORDER TZ</Text>
      <ActivityIndicator size="large" color="#0a7ea4" style={styles.spinner} />
      <Text style={styles.message}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 24,
  },
  spinner: {
    marginBottom: 16,
  },
  message: {
    fontSize: 14,
    color: '#555',
  },
});
