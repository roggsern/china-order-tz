import { useState } from 'react';
import {
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  SUPPORT_TICKET_CATEGORIES,
  type SupportTicketCategoryValue,
} from '../api/supportApi';
import { useSupportMutations } from '../hooks/useSupportTickets';

export function CreateSupportTicketScreen() {
  const { create } = useSupportMutations();
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [category, setCategory] =
    useState<SupportTicketCategoryValue>('general');

  async function onSubmit() {
    const trimmedSubject = subject.trim();
    const trimmedMessage = message.trim();
    if (!trimmedSubject || !trimmedMessage) {
      Alert.alert('Missing details', 'Subject and message are required.');
      return;
    }

    try {
      const ticket = await create.mutateAsync({
        subject: trimmedSubject,
        category,
        message: trimmedMessage,
      });
      router.replace(
        `/(app)/account/support/${encodeURIComponent(ticket.id)}` as never,
      );
    } catch (error) {
      Alert.alert(
        'Could not create ticket',
        error instanceof Error ? error.message : 'Please try again.',
      );
    }
  }

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.heading}>New support ticket</Text>

        <Text style={styles.label}>Category</Text>
        <View style={styles.chips}>
          {SUPPORT_TICKET_CATEGORIES.map((row) => {
            const selected = row.value === category;
            return (
              <Pressable
                key={row.value}
                onPress={() => setCategory(row.value)}
                style={[styles.chip, selected && styles.chipSelected]}
              >
                <Text
                  style={[
                    styles.chipText,
                    selected && styles.chipTextSelected,
                  ]}
                >
                  {row.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <Text style={styles.label}>Subject</Text>
        <TextInput
          value={subject}
          onChangeText={setSubject}
          style={styles.input}
          maxLength={200}
        />

        <Text style={styles.label}>Message</Text>
        <TextInput
          value={message}
          onChangeText={setMessage}
          style={[styles.input, styles.message]}
          multiline
          textAlignVertical="top"
          maxLength={5000}
        />

        <PrimaryButton
          label="Submit ticket"
          loading={create.isPending}
          disabled={create.isPending}
          onPress={() => void onSubmit()}
          style={styles.button}
        />
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: { backgroundColor: colors.background, flex: 1 },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  heading: { ...typography.heading, marginBottom: spacing.lg },
  label: {
    ...typography.caption,
    fontWeight: '700',
    marginBottom: spacing.xs,
    color: colors.textMuted,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.md,
  },
  chip: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    backgroundColor: colors.backgroundMuted,
  },
  chipSelected: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryMuted,
  },
  chipText: { ...typography.caption, color: colors.text },
  chipTextSelected: { color: colors.primaryPressed, fontWeight: '700' },
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
  message: { minHeight: 140 },
  button: { alignSelf: 'stretch', marginTop: spacing.md },
});
