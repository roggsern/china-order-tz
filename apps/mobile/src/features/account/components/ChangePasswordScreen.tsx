import { useState } from 'react';
import { Alert, StyleSheet, Text, TextInput } from 'react-native';
import { router } from 'expo-router';
import { logout } from '@/src/features/auth';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { changePassword } from '../api/changePasswordApi';

export function ChangePasswordScreen() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [busy, setBusy] = useState(false);

  async function onSubmit() {
    if (!currentPassword || !password || !confirmation) {
      Alert.alert('Missing fields', 'Complete all password fields.');
      return;
    }
    if (password !== confirmation) {
      Alert.alert('Mismatch', 'New password and confirmation must match.');
      return;
    }
    if (password.length < 8) {
      Alert.alert('Too short', 'New password must be at least 8 characters.');
      return;
    }

    setBusy(true);
    try {
      const result = await changePassword({
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      });

      Alert.alert(
        'Password updated',
        result.message || 'Please sign in again with your new password.',
        [
          {
            text: 'Sign in',
            onPress: () => {
              void (async () => {
                await logout();
                router.replace('/(auth)/login');
              })();
            },
          },
        ],
      );
    } catch (error) {
      Alert.alert(
        'Could not update password',
        error instanceof Error ? error.message : 'Please try again.',
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScreenContainer style={styles.screen}>
      <Text style={styles.heading}>Change password</Text>
      <Text style={styles.caption}>
        After you change your password, you will need to sign in again.
      </Text>

      <Text style={styles.label}>Current password</Text>
      <TextInput
        value={currentPassword}
        onChangeText={setCurrentPassword}
        secureTextEntry
        style={styles.input}
        autoCapitalize="none"
        autoCorrect={false}
      />

      <Text style={styles.label}>New password</Text>
      <TextInput
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        style={styles.input}
        autoCapitalize="none"
        autoCorrect={false}
      />

      <Text style={styles.label}>Confirm new password</Text>
      <TextInput
        value={confirmation}
        onChangeText={setConfirmation}
        secureTextEntry
        style={styles.input}
        autoCapitalize="none"
        autoCorrect={false}
      />

      <PrimaryButton
        label="Update password"
        loading={busy}
        disabled={busy}
        onPress={() => void onSubmit()}
        style={styles.button}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: { backgroundColor: colors.background },
  heading: { ...typography.heading, marginBottom: spacing.sm },
  caption: { ...typography.caption, marginBottom: spacing.lg },
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
