import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import {
  getAuthErrorMessage,
  getAuthFieldErrors,
  registerAccount,
  registerRequestSchema,
} from '@/src/features/auth';
import { AuthCard } from '@/src/features/auth/components/AuthCard';
import { AuthErrorBanner } from '@/src/features/auth/components/AuthBanners';
import { AuthField } from '@/src/features/auth/components/AuthField';
import { AuthFooterLink } from '@/src/features/auth/components/AuthFooterLink';
import { AuthHeader } from '@/src/features/auth/components/AuthHeader';
import { AuthLegalNote } from '@/src/features/auth/components/AuthLegalNote';
import { AuthPasswordField } from '@/src/features/auth/components/AuthPasswordField';
import { AuthShell } from '@/src/features/auth/components/AuthShell';
import {
  buildLoginHref,
  sanitizeAuthReturnTo,
} from '@/src/features/cart/utils/authReturn';
import { AUTH_HOME_HREF } from '@/src/features/auth/components/authRoutes';
import { PrimaryButton } from '@/src/shared/ui';

export default function RegisterScreen() {
  const params = useLocalSearchParams<{ returnTo?: string | string[] }>();
  const returnToRaw = Array.isArray(params.returnTo)
    ? params.returnTo[0]
    : params.returnTo;
  const returnTo = sanitizeAuthReturnTo(returnToRaw);

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function onSubmit() {
    setFormError(null);
    setFieldErrors({});

    const parsed = registerRequestSchema.safeParse({
      name,
      email,
      phone: phone.trim() || undefined,
      password,
      password_confirmation: passwordConfirmation,
    });

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
      await registerAccount(parsed.data);
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
        title="Create your account"
        subtitle="Shop from China and trusted Tanzania stores with one customer account."
      />

      <AuthCard>
        {formError ? <AuthErrorBanner message={formError} /> : null}

        <AuthField
          label="Full name"
          value={name}
          onChangeText={setName}
          error={fieldErrors.name}
          editable={!submitting}
          autoCapitalize="words"
          autoComplete="name"
          textContentType="name"
          accessibilityLabel="Name"
        />

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

        <AuthField
          label="Phone"
          value={phone}
          onChangeText={setPhone}
          error={fieldErrors.phone}
          helperText="Optional. Used for order updates."
          editable={!submitting}
          keyboardType="phone-pad"
          autoComplete="tel"
          textContentType="telephoneNumber"
          accessibilityLabel="Phone (optional)"
        />

        <AuthPasswordField
          label="Password"
          value={password}
          onChangeText={setPassword}
          error={fieldErrors.password}
          helperText="Use at least 8 characters."
          editable={!submitting}
          isNewPassword
          accessibilityLabel="Password"
        />

        <AuthPasswordField
          label="Confirm password"
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          error={fieldErrors.password_confirmation}
          editable={!submitting}
          isNewPassword
          accessibilityLabel="Confirm password"
        />

        <PrimaryButton
          label="Create account"
          loading={submitting}
          onPress={() => void onSubmit()}
          accessibilityLabel="Create account"
        />

        <AuthLegalNote />
      </AuthCard>

      <AuthFooterLink
        prompt="Already have an account?"
        actionLabel="Sign in"
        href={buildLoginHref(returnTo) as never}
        disabled={submitting}
      />
    </AuthShell>
  );
}
