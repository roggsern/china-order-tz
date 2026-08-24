import { useState } from 'react';
import { router } from 'expo-router';
import { getAuthErrorMessage, getAuthFieldErrors } from '@/src/features/auth';
import { AuthCard } from '@/src/features/auth/components/AuthCard';
import {
  AuthErrorBanner,
  AuthSuccessBanner,
} from '@/src/features/auth/components/AuthBanners';
import { AuthField } from '@/src/features/auth/components/AuthField';
import { AuthFooterLink } from '@/src/features/auth/components/AuthFooterLink';
import { AuthHeader } from '@/src/features/auth/components/AuthHeader';
import { AuthShell } from '@/src/features/auth/components/AuthShell';
import {
  forgotPasswordRequestSchema,
  requestPasswordReset,
} from '@/src/features/auth/api/forgotPasswordApi';
import { buildAuthWebUrl } from '@/src/features/auth/utils/authWebLinks';
import { AUTH_LOGIN_HREF } from '@/src/features/auth/components/authRoutes';
import { PrimaryButton } from '@/src/shared/ui';

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
    <AuthShell>
      <AuthHeader
        title="Forgot password"
        subtitle="Enter your account email. If an account exists, we will send reset instructions. The secure reset link opens on chinaordertz.com."
      />

      <AuthCard>
        {formError ? <AuthErrorBanner message={formError} /> : null}

        {successMessage ? (
          <AuthSuccessBanner
            message={successMessage}
            hint={`Check your inbox and spam folder. After resetting, return here to sign in. Reset page: ${buildAuthWebUrl('/reset-password')}`}
          />
        ) : null}

        {!successMessage ? (
          <>
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
              accessibilityLabel="Email address"
            />

            <PrimaryButton
              label="Send reset instructions"
              loading={submitting}
              onPress={() => void onSubmit()}
              accessibilityLabel="Send password reset instructions"
            />
          </>
        ) : (
          <PrimaryButton
            label="Back to sign in"
            onPress={() => router.replace(AUTH_LOGIN_HREF as never)}
            accessibilityLabel="Back to sign in"
          />
        )}
      </AuthCard>

      {!successMessage ? (
        <AuthFooterLink
          prompt=""
          actionLabel="Back to sign in"
          href={AUTH_LOGIN_HREF}
          disabled={submitting}
        />
      ) : null}
    </AuthShell>
  );
}
