import { useMemo, useState } from 'react';
import { Alert, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  useAddressMutations,
  useCustomerAddresses,
} from '../hooks/useCustomerAddresses';
import type { AddressInput, CustomerAddress } from '../api/addressesApi';

type FormState = {
  recipientName: string;
  phone: string;
  label: string;
  street: string;
  district: string;
  city: string;
  region: string;
  postalCode: string;
  country: string;
};

function formFromAddress(address: CustomerAddress | null): FormState {
  if (!address) {
    return {
      recipientName: '',
      phone: '',
      label: '',
      street: '',
      district: '',
      city: '',
      region: '',
      postalCode: '',
      country: 'Tanzania',
    };
  }
  return {
    recipientName: address.recipientName,
    phone: address.phone,
    label: address.label ?? '',
    street: address.street,
    district: address.district ?? '',
    city: address.city,
    region: address.region,
    postalCode: address.postalCode ?? '',
    country: address.country ?? 'Tanzania',
  };
}

function AddressFormFields({
  addressId,
  existing,
}: {
  addressId: string | null;
  existing: CustomerAddress | null;
}) {
  const mutations = useAddressMutations();
  const [form, setForm] = useState<FormState>(() => formFromAddress(existing));
  const busy = mutations.create.isPending || mutations.update.isPending;

  function updateField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function onSave() {
    const input: AddressInput = {
      recipient_name: form.recipientName.trim(),
      phone: form.phone.trim(),
      label: form.label.trim() || null,
      street: form.street.trim(),
      district: form.district.trim(),
      city: form.city.trim(),
      region: form.region.trim(),
      postal_code: form.postalCode.trim() || null,
      country: form.country.trim() || 'Tanzania',
      is_default: existing?.isDefault ?? false,
    };

    if (
      !input.recipient_name ||
      !input.phone ||
      !input.street ||
      !input.district ||
      !input.city ||
      !input.region
    ) {
      Alert.alert('Missing details', 'Please complete all required fields.');
      return;
    }

    try {
      if (addressId) {
        await mutations.update.mutateAsync({ id: addressId, input });
      } else {
        await mutations.create.mutateAsync({ ...input, is_default: true });
      }
      router.back();
    } catch {
      Alert.alert('Unable to save', 'Check the details and try again.');
    }
  }

  const fields: [keyof FormState, string][] = [
    ['recipientName', 'Recipient name'],
    ['phone', 'Phone'],
    ['label', 'Label (optional)'],
    ['street', 'Street'],
    ['district', 'District'],
    ['city', 'City'],
    ['region', 'Region'],
    ['postalCode', 'Postal code'],
    ['country', 'Country'],
  ];

  return (
    <>
      <Text style={styles.heading}>
        {addressId ? 'Edit address' : 'Add address'}
      </Text>
      {fields.map(([key, fieldLabel]) => (
        <View key={key} style={styles.field}>
          <Text style={styles.label}>{fieldLabel}</Text>
          <TextInput
            value={form[key]}
            onChangeText={(value) => updateField(key, value)}
            style={styles.input}
            autoCapitalize="words"
            editable={!busy}
          />
        </View>
      ))}
      <PrimaryButton
        label={busy ? 'Saving…' : 'Save address'}
        onPress={() => void onSave()}
        disabled={busy}
        style={styles.save}
      />
    </>
  );
}

export function AddressFormScreen() {
  const params = useLocalSearchParams<{ id?: string }>();
  const addressId = typeof params.id === 'string' ? params.id : null;
  const query = useCustomerAddresses();

  const existing = useMemo(
    () => query.data?.addresses.find((row) => row.id === addressId) ?? null,
    [addressId, query.data?.addresses],
  );

  if (addressId && query.isLoading) {
    return (
      <ScreenContainer>
        <Text style={styles.loading}>Loading address…</Text>
      </ScreenContainer>
    );
  }

  // Remount when existing address resolves so initial state is correct.
  const formKey = addressId ? `${addressId}:${existing?.id ?? 'pending'}` : 'new';

  return (
    <ScreenContainer scroll contentStyle={styles.content}>
      <AddressFormFields
        key={formKey}
        addressId={addressId}
        existing={existing}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: {
    paddingBottom: spacing.huge,
  },
  heading: {
    ...typography.heading,
    marginBottom: spacing.lg,
  },
  field: {
    marginBottom: spacing.md,
  },
  label: {
    ...typography.caption,
    marginBottom: spacing.xs,
    fontWeight: '700',
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm + 2,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.surface,
  },
  save: {
    marginTop: spacing.md,
    alignSelf: 'stretch',
  },
  loading: {
    ...typography.body,
    padding: spacing.lg,
  },
});
