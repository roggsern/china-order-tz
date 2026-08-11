import { Link, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';

import {
  getAuthErrorMessage,
  getAuthFieldErrors,
  registerAccount,
  registerRequestSchema,
} from '@/src/features/auth';
import { authStyles as styles } from '@/src/features/auth/components/authStyles';
import {
  buildLoginHref,
  sanitizeAuthReturnTo,
} from '@/src/features/cart/utils/authReturn';

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
        router.replace('/(app)/(tabs)/home');
      }
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
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <Text style={styles.heading}>Register</Text>
        <Text style={styles.subheading}>Create a customer account.</Text>

        {formError ? (
          <View style={styles.banner}>
            <Text style={styles.bannerText}>{formError}</Text>
          </View>
        ) : null}

        <Text style={styles.label}>Name</Text>
        <TextInput
          style={[styles.input, fieldErrors.name ? styles.inputError : null]}
          value={name}
          onChangeText={setName}
          editable={!submitting}
        />
        {fieldErrors.name ? <Text style={styles.fieldError}>{fieldErrors.name}</Text> : <View style={styles.fieldSpacer} />}

        <Text style={styles.label}>Email</Text>
        <TextInput
          style={[styles.input, fieldErrors.email ? styles.inputError : null]}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          textContentType="emailAddress"
          value={email}
          onChangeText={setEmail}
          editable={!submitting}
        />
        {fieldErrors.email ? <Text style={styles.fieldError}>{fieldErrors.email}</Text> : <View style={styles.fieldSpacer} />}

        <Text style={styles.label}>Phone (optional)</Text>
        <TextInput
          style={[styles.input, fieldErrors.phone ? styles.inputError : null]}
          keyboardType="phone-pad"
          textContentType="telephoneNumber"
          value={phone}
          onChangeText={setPhone}
          editable={!submitting}
        />
        {fieldErrors.phone ? <Text style={styles.fieldError}>{fieldErrors.phone}</Text> : <View style={styles.fieldSpacer} />}

        <Text style={styles.label}>Password</Text>
        <TextInput
          style={[styles.input, fieldErrors.password ? styles.inputError : null]}
          secureTextEntry
          textContentType="newPassword"
          value={password}
          onChangeText={setPassword}
          editable={!submitting}
        />
        {fieldErrors.password ? (
          <Text style={styles.fieldError}>{fieldErrors.password}</Text>
        ) : (
          <View style={styles.fieldSpacer} />
        )}

        <Text style={styles.label}>Confirm password</Text>
        <TextInput
          style={[styles.input, fieldErrors.password_confirmation ? styles.inputError : null]}
          secureTextEntry
          textContentType="newPassword"
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          editable={!submitting}
        />
        {fieldErrors.password_confirmation ? (
          <Text style={styles.fieldError}>{fieldErrors.password_confirmation}</Text>
        ) : (
          <View style={styles.fieldSpacer} />
        )}

        <Pressable
          style={[styles.button, submitting ? styles.buttonDisabled : null]}
          onPress={() => void onSubmit()}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Create account</Text>
          )}
        </Pressable>

        <View style={styles.linkRow}>
          <Link href={buildLoginHref(returnTo) as never} asChild>
            <Pressable disabled={submitting}>
              <Text style={styles.linkText}>Already have an account? Sign in</Text>
            </Pressable>
          </Link>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
