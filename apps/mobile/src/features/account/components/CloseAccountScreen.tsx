import { useState } from 'react';
import {
  Alert,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { logout } from '@/src/features/auth';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { closeAccount } from '../api/closeAccountApi';

export function CloseAccountScreen() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [acknowledge, setAcknowledge] = useState(false);
  const [busy, setBusy] = useState(false);

  async function onSubmit() {
    if (!currentPassword.trim()) {
      Alert.alert('Password required', 'Enter your current password to continue.');
      return;
    }
    if (!acknowledge) {
      Alert.alert(
        'Confirmation required',
        'Confirm that you understand account closure ends access and cannot be undone from this screen.',
      );
      return;
    }

    setBusy(true);
    try {
      const result = await closeAccount({
        current_password: currentPassword,
        acknowledge: true,
      });

      Alert.alert(
        'Account closed',
        result.message ||
          'Your account has been closed. Some order and payment records may be kept where required.',
        [
          {
            text: 'Continue',
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
        'Could not close account',
        error instanceof Error ? error.message : 'Please try again.',
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScreenContainer style={styles.screen}>
      <Text style={styles.heading}>Close account</Text>
      <Text style={styles.caption}>
        This permanently ends signed-in access for this account. Some order,
        payment, refund, and fulfillment records may be retained for legitimate
        operational, accounting, or legal purposes.
      </Text>

      <Text style={styles.label}>Current password</Text>
      <TextInput
        value={currentPassword}
        onChangeText={setCurrentPassword}
        secureTextEntry
        style={styles.input}
        autoCapitalize="none"
        autoCorrect={false}
        editable={!busy}
      />

      <View style={styles.ackRow}>
        <Switch
          value={acknowledge}
          onValueChange={setAcknowledge}
          disabled={busy}
          trackColor={{ true: colors.error, false: colors.border }}
        />
        <Text style={styles.ackText}>
          I understand that closing my account cannot be undone from this screen.
        </Text>
      </View>

      <PrimaryButton
        label={busy ? 'Closing…' : 'Close my account'}
        loading={busy}
        disabled={busy}
        onPress={() => void onSubmit()}
        style={styles.danger}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: {
    backgroundColor: colors.background,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
  },
  heading: {
    ...typography.heading,
    color: colors.error,
  },
  caption: {
    ...typography.caption,
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
  },
  label: {
    ...typography.caption,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: colors.surface,
    color: colors.text,
    marginBottom: spacing.lg,
  },
  ackRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.xl,
  },
  ackText: {
    ...typography.caption,
    flex: 1,
    color: colors.text,
  },
  danger: {
    alignSelf: 'stretch',
    backgroundColor: colors.error,
  },
});
