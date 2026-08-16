import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Link, router } from 'expo-router';
import { getAuthErrorMessage, getAuthFieldErrors } from '@/src/features/auth';
import { authStyles as styles } from '@/src/features/auth/components/authStyles';
import {
  forgotPasswordRequestSchema,
  requestPasswordReset,
} from '@/src/features/auth/api/forgotPasswordApi';
import { buildAuthWebUrl } from '@/src/features/auth/utils/authWebLinks';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

export function ForgotPasswordScreen() {
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  async function onSubmit() {
    setFormError(null);
    setFieldErrors({});
    setSuccessMessage(null);

    const parsed = forgotPasswordRequestSchema.safeParse({ email });
    if (!parsed.success) {
      const next: Record<string, string> = {};
      for (const issue of parsed.error.issues) {
        const key = String(issue.path[0] ?? 'form');
        if (!next[key]) next[key] = issue.message;
      }
      setFieldErrors(next);
      return;
    }

    setSubmitting(true);
    try {
      const result = await requestPasswordReset(parsed.data);
      setSuccessMessage(result.message);
    } catch (error) {
      setFormError(getAuthErrorMessage(error));
      setFieldErrors(getAuthFieldErrors(error));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.screen}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={styles.heading} accessibilityRole="header">
          Forgot password
        </Text>
        <Text style={styles.subheading}>
          Enter your account email. If an account exists, we will send reset
          instructions. The secure reset link opens on chinaordertz.com.
        </Text>

        {formError ? (
          <View style={styles.banner} accessibilityRole="alert">
            <Text style={styles.bannerText}>{formError}</Text>
          </View>
        ) : null}

        {successMessage ? (
          <View
            style={successStyles.banner}
            accessibilityRole="text"
            accessibilityLiveRegion="polite"
          >
            <Text style={successStyles.text}>{successMessage}</Text>
            <Text style={successStyles.hint}>
              Check your inbox and spam folder. After resetting, return here to
              sign in. Reset page: {buildAuthWebUrl('/reset-password')}
            </Text>
          </View>
        ) : null}

        {!successMessage ? (
          <>
            <Text style={styles.label}>Email</Text>
            <TextInput
              style={[styles.input, fieldErrors.email ? styles.inputError : null]}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              textContentType="emailAddress"
              autoComplete="email"
              value={email}
              onChangeText={setEmail}
              editable={!submitting}
              accessibilityLabel="Email address"
            />
            {fieldErrors.email ? (
              <Text style={styles.fieldError}>{fieldErrors.email}</Text>
            ) : (
              <View style={styles.fieldSpacer} />
            )}

            <Pressable
              style={[styles.button, submitting ? styles.buttonDisabled : null]}
              onPress={() => void onSubmit()}
              disabled={submitting}
              accessibilityRole="button"
              accessibilityLabel="Send password reset instructions"
            >
              {submitting ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.buttonText}>Send reset instructions</Text>
              )}
            </Pressable>
          </>
        ) : (
          <Pressable
            style={styles.button}
            onPress={() => router.replace('/(auth)/login')}
            accessibilityRole="button"
            accessibilityLabel="Back to sign in"
          >
            <Text style={styles.buttonText}>Back to sign in</Text>
          </Pressable>
        )}

        <View style={styles.linkRow}>
          <Link href="/(auth)/login" asChild>
            <Pressable disabled={submitting} accessibilityRole="link">
              <Text style={styles.linkText}>Back to sign in</Text>
            </Pressable>
          </Link>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const successStyles = StyleSheet.create({
  banner: {
    backgroundColor: colors.successMuted,
    borderRadius: radius.lg,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  text: {
    ...typography.bodyStrong,
    color: colors.success,
  },
  hint: {
    ...typography.caption,
    marginTop: spacing.sm,
    color: colors.textSecondary,
  },
});
