import { ApiError } from '@/src/core/api';
import { login } from '@/src/core/auth';
import { PrimaryButton, ScreenHeader } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';
import { router } from 'expo-router';
import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleLogin() {
    setError(null);
    setLoading(true);
    try {
      await login(email, password);
      router.replace('/(app)/(tabs)/dashboard');
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError('Unable to sign in. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.container}
      >
        <View style={styles.hero}>
          <Text style={styles.brand}>CHINA ORDER TZ</Text>
          <Text style={styles.brandSub}>Admin Operations</Text>
        </View>

        <View style={styles.card}>
          <ScreenHeader title="Sign in" subtitle="Use your admin credentials" />

          <Text style={styles.label}>Email</Text>
          <TextInput
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            value={email}
            onChangeText={setEmail}
            style={styles.input}
            placeholder="admin@chinaordertz.com"
            placeholderTextColor={colors.textMuted}
          />

          <Text style={styles.label}>Password</Text>
          <TextInput
            secureTextEntry
            value={password}
            onChangeText={setPassword}
            style={styles.input}
            placeholder="••••••••"
            placeholderTextColor={colors.textMuted}
          />

          {error ? <Text style={styles.error}>{error}</Text> : null}

          <PrimaryButton label="Sign in" onPress={handleLogin} loading={loading} />

          <Pressable onPress={() => setPassword('')} style={styles.hintWrap}>
            <Text style={styles.hint}>Secure admin session — tokens stored in device keychain only.</Text>
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy },
  container: { flex: 1, justifyContent: 'center', padding: spacing.xl },
  hero: { marginBottom: spacing.xl },
  brand: { color: colors.gold, fontSize: 24, fontWeight: '800', letterSpacing: 1 },
  brandSub: { color: '#cbd5e1', marginTop: spacing.xs, fontSize: 14 },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.lg,
    padding: spacing.xl,
    gap: spacing.sm,
  },
  label: { marginTop: spacing.sm, fontSize: 13, fontWeight: '600', color: colors.text },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radii.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    fontSize: 15,
    color: colors.text,
    backgroundColor: colors.surface,
  },
  error: { color: colors.danger, fontSize: 13, marginTop: spacing.sm },
  hintWrap: { marginTop: spacing.md },
  hint: { color: colors.textMuted, fontSize: 12, textAlign: 'center' },
});
