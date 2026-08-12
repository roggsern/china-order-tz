import { useMemo, useState } from 'react';
import { Alert, StyleSheet, Text, TextInput } from 'react-native';
import { router } from 'expo-router';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  useCustomerProfile,
  useUpdateProfileMutation,
} from '../hooks/useCustomerProfile';

export function EditProfileScreen() {
  const profileQuery = useCustomerProfile();
  const updateMutation = useUpdateProfileMutation();

  const initial = useMemo(() => {
    const profile = profileQuery.data;
    return {
      firstName: profile?.firstName ?? '',
      lastName: profile?.lastName ?? '',
      phone: profile?.phone ?? '',
    };
  }, [profileQuery.data]);

  const [firstName, setFirstName] = useState<string | null>(null);
  const [lastName, setLastName] = useState<string | null>(null);
  const [phone, setPhone] = useState<string | null>(null);

  const form = {
    firstName: firstName ?? initial.firstName,
    lastName: lastName ?? initial.lastName,
    phone: phone ?? initial.phone,
  };

  if (profileQuery.isLoading && !profileQuery.data) {
    return <ScreenLoadingState label="Loading profile…" />;
  }

  if (profileQuery.isError && !profileQuery.data) {
    return (
      <EmptyState
        title="Profile unavailable"
        message="We could not load your profile. Please try again."
        actionLabel="Retry"
        onActionPress={() => void profileQuery.refetch()}
      />
    );
  }

  async function onSave() {
    const first = form.firstName.trim();
    const last = form.lastName.trim();
    const phoneValue = form.phone.trim();

    if (!first || !last) {
      Alert.alert('Missing name', 'First and last name are required.');
      return;
    }

    try {
      await updateMutation.mutateAsync({
        first_name: first,
        last_name: last,
        phone: phoneValue || null,
      });
      Alert.alert('Saved', 'Your profile was updated.');
      router.back();
    } catch (error) {
      Alert.alert(
        'Could not save',
        error instanceof Error ? error.message : 'Please try again.',
      );
    }
  }

  return (
    <ScreenContainer style={styles.screen}>
      <Text style={styles.heading}>Edit profile</Text>
      <Text style={styles.caption}>
        Email changes stay on the website for verification. Name and phone update
        here via the profile API.
      </Text>

      {profileQuery.data?.email ? (
        <Text style={styles.email}>Email: {profileQuery.data.email}</Text>
      ) : null}

      <Text style={styles.label}>First name</Text>
      <TextInput
        value={form.firstName}
        onChangeText={setFirstName}
        style={styles.input}
        autoCapitalize="words"
        autoCorrect={false}
      />

      <Text style={styles.label}>Last name</Text>
      <TextInput
        value={form.lastName}
        onChangeText={setLastName}
        style={styles.input}
        autoCapitalize="words"
        autoCorrect={false}
      />

      <Text style={styles.label}>Phone</Text>
      <TextInput
        value={form.phone}
        onChangeText={setPhone}
        style={styles.input}
        keyboardType="phone-pad"
        autoCorrect={false}
        placeholder="+255…"
        placeholderTextColor={colors.textMuted}
      />

      <PrimaryButton
        label="Save profile"
        loading={updateMutation.isPending}
        disabled={updateMutation.isPending}
        onPress={() => void onSave()}
        style={styles.button}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: { backgroundColor: colors.background },
  heading: { ...typography.heading, marginBottom: spacing.sm },
  caption: { ...typography.caption, marginBottom: spacing.lg },
  email: { ...typography.body, marginBottom: spacing.md },
  label: {
    ...typography.caption,
    fontWeight: '700',
    marginBottom: spacing.xs,
    color: colors.textMuted,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.backgroundMuted,
  },
  button: { alignSelf: 'stretch', marginTop: spacing.md },
});
