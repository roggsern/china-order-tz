import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type {
  ApplyShippingChoiceInput,
  CheckoutShippingChoiceValue,
  ShippingChoiceOption,
} from '../models/types';
import { visibleShippingChoices } from '../utils/mapCheckout';

type Props = {
  options: ShippingChoiceOption[];
  submitting?: boolean;
  currentChoice?: string | null;
  currentMethod?: string | null;
  onSubmit: (input: ApplyShippingChoiceInput) => void;
};

export function ShippingChoicePicker({
  options,
  submitting,
  currentChoice,
  currentMethod,
  onSubmit,
}: Props) {
  const [choice, setChoice] = useState<CheckoutShippingChoiceValue | null>(
    (currentChoice as CheckoutShippingChoiceValue) ?? null,
  );
  const [method, setMethod] = useState<'air' | 'sea' | null>(
    currentMethod === 'air' || currentMethod === 'sea' ? currentMethod : null,
  );
  const [agentName, setAgentName] = useState('');
  const [agentContact, setAgentContact] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);
  const visibleOptions = visibleShippingChoices(options);

  function handleSubmit() {
    if (submitting) return;
    setLocalError(null);
    if (!choice) {
      setLocalError('Select a shipping option to continue.');
      return;
    }
    if (choice === 'company_shipping' && !method) {
      setLocalError('Company shipping requires air or sea.');
      return;
    }
    if (choice === 'customer_agent' && (!agentName.trim() || !agentContact.trim())) {
      setLocalError('Agent name and contact are required.');
      return;
    }

    onSubmit({
      shippingChoice: choice,
      shippingMethod: choice === 'company_shipping' ? method : null,
      agentName: choice === 'customer_agent' ? agentName : null,
      agentContact: choice === 'customer_agent' ? agentContact : null,
    });
  }

  if (visibleOptions.length === 0) {
    return (
      <Text style={styles.note}>
        No shipping options are available for this cart. Check your cart and try again.
      </Text>
    );
  }

  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Shipping option</Text>
      <Text style={styles.note}>
        Shipping options depend on what is in your cart. Fees update after you save.
      </Text>

      {visibleOptions.map((option) => {
        const active = choice === option.value;
        return (
          <Pressable
            key={option.value}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            style={[styles.option, active ? styles.optionActive : null]}
            onPress={() => setChoice(option.value)}
            disabled={submitting}
          >
            <Text style={[styles.optionText, active ? styles.optionTextActive : null]}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}

      {choice === 'company_shipping' ? (
        <View style={styles.methodRow}>
          {(['air', 'sea'] as const).map((value) => {
            const active = method === value;
            return (
              <Pressable
                key={value}
                accessibilityRole="button"
                accessibilityState={{ selected: active }}
                style={[styles.methodChip, active ? styles.optionActive : null]}
                onPress={() => setMethod(value)}
                disabled={submitting}
              >
                <Text style={[styles.optionText, active ? styles.optionTextActive : null]}>
                  {value === 'air' ? 'Air' : 'Sea'}
                </Text>
              </Pressable>
            );
          })}
        </View>
      ) : null}

      {choice === 'customer_agent' ? (
        <View style={styles.agentFields}>
          <TextInput
            style={styles.input}
            placeholder="Agent name"
            placeholderTextColor={colors.textSubtle}
            value={agentName}
            onChangeText={setAgentName}
          />
          <TextInput
            style={styles.input}
            placeholder="Agent contact"
            placeholderTextColor={colors.textSubtle}
            value={agentContact}
            onChangeText={setAgentContact}
          />
        </View>
      ) : null}

      {localError ? <Text style={styles.error}>{localError}</Text> : null}

      <PrimaryButton
        label="Save shipping choice"
        loading={submitting}
        disabled={submitting}
        onPress={handleSubmit}
        style={styles.button}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  title: { ...typography.title, fontSize: 16 },
  note: {
    marginTop: spacing.xs,
    marginBottom: spacing.md,
    ...typography.caption,
  },
  option: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    marginBottom: spacing.sm,
    backgroundColor: colors.surface,
  },
  optionActive: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryMuted,
  },
  optionText: {
    ...typography.bodyStrong,
    color: colors.textSecondary,
  },
  optionTextActive: {
    color: colors.primaryPressed,
    fontWeight: '700',
  },
  methodRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  methodChip: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingVertical: spacing.sm + 2,
    alignItems: 'center',
    backgroundColor: colors.surface,
  },
  agentFields: { gap: spacing.sm, marginBottom: spacing.sm },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm + 2,
    ...typography.bodyStrong,
    color: colors.text,
    backgroundColor: colors.surface,
  },
  error: { ...typography.caption, color: colors.error, marginBottom: spacing.sm },
  button: { alignSelf: 'stretch' },
});
