import { Link, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getAuthErrorMessage,
  getAuthFieldErrors,
  loginRequestSchema,
  loginWithPassword,
} from '@/src/features/auth';
import { AuthCard } from '@/src/features/auth/components/AuthCard';
import { AuthErrorBanner } from '@/src/features/auth/components/AuthBanners';
import { AuthField } from '@/src/features/auth/components/AuthField';
import { AuthFooterLink } from '@/src/features/auth/components/AuthFooterLink';
import { AuthHeader } from '@/src/features/auth/components/AuthHeader';
import { AuthPasswordField } from '@/src/features/auth/components/AuthPasswordField';
import { AuthShell } from '@/src/features/auth/components/AuthShell';
import { AUTH_FORGOT_PASSWORD_HREF, AUTH_HOME_HREF } from '@/src/features/auth/components/authRoutes';
import {
  buildRegisterHref,
  sanitizeAuthReturnTo,
} from '@/src/features/cart/utils/authReturn';
import { PrimaryButton } from '@/src/shared/ui';
import { colors, spacing, typography } from '@/src/shared/theme';

export default function LoginScreen() {
  const params = useLocalSearchParams<{ returnTo?: string | string[] }>();
  const returnToRaw = Array.isArray(params.returnTo)
    ? params.returnTo[0]
    : params.returnTo;
  const returnTo = sanitizeAuthReturnTo(returnToRaw);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function onSubmit() {
    setFormError(null);
    setFieldErrors({});

    const parsed = loginRequestSchema.safeParse({ email, password });
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
      await loginWithPassword(parsed.data);
      if (returnTo) {
        router.replace(returnTo as never);
      } else {
        router.replace(AUTH_HOME_HREF as never);
      }
    } catch (error) {
      setFormError(getAuthErrorMessage(error));
      setFieldErrors(getAuthFieldErrors(error));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell>
      <AuthHeader
        title="Welcome back"
        subtitle="Sign in to your CHINA ORDER TZ account."
      />

      <AuthCard>
        {formError ? <AuthErrorBanner message={formError} /> : null}

        <AuthField
          label="Email"
          value={email}
          onChangeText={setEmail}
          error={fieldErrors.email}
          editable={!submitting}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          autoComplete="email"
          textContentType="emailAddress"
          accessibilityLabel="Email"
        />

        <AuthPasswordField
          label="Password"
          value={password}
          onChangeText={setPassword}
          error={fieldErrors.password}
          editable={!submitting}
          accessibilityLabel="Password"
        />

        <View style={styles.forgotRow}>
          <Link href={AUTH_FORGOT_PASSWORD_HREF} asChild>
            <Pressable
              disabled={submitting}
              accessibilityRole="link"
              accessibilityLabel="Forgot password?"
            >
              <Text style={styles.forgot}>Forgot password?</Text>
            </Pressable>
          </Link>
        </View>

        <PrimaryButton
          label="Sign in"
          loading={submitting}
          onPress={() => void onSubmit()}
          accessibilityLabel="Sign in"
        />
      </AuthCard>

      <AuthFooterLink
        prompt="New to CHINA ORDER TZ?"
        actionLabel="Create account"
        href={buildRegisterHref(returnTo) as never}
        disabled={submitting}
      />
    </AuthShell>
  );
}

const styles = StyleSheet.create({
  forgotRow: {
    alignItems: 'flex-end',
    marginBottom: spacing.lg,
  },
  forgot: {
    ...typography.bodyStrong,
    color: colors.primaryPressed,
  },
});
