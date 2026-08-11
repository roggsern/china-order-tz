import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type {
  ApplyShippingChoiceInput,
  CheckoutShippingChoiceValue,
  ShippingChoiceOption,
} from '../models/types';

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

  function handleSubmit() {
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

  if (options.length === 0) {
    return (
      <Text style={styles.note}>
        No shipping choices available for this cart. Check cart channel and try again.
      </Text>
    );
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Shipping / receiving</Text>
      <Text style={styles.note}>
        Options follow your cart’s commerce channel. The server validates the final choice.
      </Text>

      {options.map((option) => {
        const active = choice === option.value;
        return (
          <Pressable
            key={option.value}
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
            placeholderTextColor="#999"
            value={agentName}
            onChangeText={setAgentName}
          />
          <TextInput
            style={styles.input}
            placeholder="Agent contact"
            placeholderTextColor="#999"
            value={agentContact}
            onChangeText={setAgentContact}
          />
        </View>
      ) : null}

      {localError ? <Text style={styles.error}>{localError}</Text> : null}

      <Pressable
        style={[styles.button, submitting ? styles.disabled : null]}
        disabled={submitting}
        onPress={handleSubmit}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Save shipping choice</Text>
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 16 },
  title: { fontSize: 16, fontWeight: '700', color: '#222' },
  note: { marginTop: 4, marginBottom: 10, fontSize: 12, color: '#666', lineHeight: 17 },
  option: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 12,
    marginBottom: 8,
    backgroundColor: '#fff',
  },
  optionActive: {
    borderColor: '#0a7ea4',
    backgroundColor: '#e7f5fa',
  },
  optionText: { fontSize: 14, color: '#333', fontWeight: '500' },
  optionTextActive: { color: '#0a7ea4', fontWeight: '700' },
  methodRow: { flexDirection: 'row', gap: 8, marginBottom: 10 },
  methodChip: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingVertical: 10,
    alignItems: 'center',
  },
  agentFields: { gap: 8, marginBottom: 8 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: '#222',
  },
  error: { color: '#b00020', marginBottom: 8, fontSize: 13 },
  button: {
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  disabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
