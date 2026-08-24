import { useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';
import { Card } from '@/src/shared/ui/Card';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import { deliveryAddressInputSchema } from '../api/schemas';
import type { CheckoutDeliveryAddress, DeliveryAddressInput } from '../models/types';

type Props = {
  initial?: CheckoutDeliveryAddress | null;
  submitting?: boolean;
  onSubmit: (input: DeliveryAddressInput) => void;
};

export function DeliveryAddressForm({ initial, submitting, onSubmit }: Props) {
  const [recipientName, setRecipientName] = useState(initial?.recipientName ?? '');
  const [phone, setPhone] = useState(initial?.phone ?? '');
  const [country, setCountry] = useState(initial?.country ?? 'Tanzania');
  const [region, setRegion] = useState(initial?.region ?? '');
  const [city, setCity] = useState(initial?.city ?? '');
  const [district, setDistrict] = useState(initial?.district ?? '');
  const [street, setStreet] = useState(initial?.street ?? '');
  const [landmark, setLandmark] = useState(initial?.landmark ?? '');
  const [postalCode, setPostalCode] = useState(initial?.postalCode ?? '');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  function handleSubmit() {
    const parsed = deliveryAddressInputSchema.safeParse({
      recipientName,
      phone,
      country,
      region,
      city,
      district,
      street,
      landmark: landmark || null,
      postalCode: postalCode || null,
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
    setFieldErrors({});
    onSubmit(parsed.data);
  }

  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Delivery address</Text>
      <Text style={styles.note}>
        Customer address is required for checkout. It is not used as a delivery engine.
      </Text>

      <Field label="Recipient" value={recipientName} onChangeText={setRecipientName} error={fieldErrors.recipientName} />
      <Field label="Phone" value={phone} onChangeText={setPhone} error={fieldErrors.phone} placeholder="+255..." />
      <Field label="Country" value={country} onChangeText={setCountry} error={fieldErrors.country} />
      <Field label="Region" value={region} onChangeText={setRegion} error={fieldErrors.region} />
      <Field label="City" value={city} onChangeText={setCity} error={fieldErrors.city} />
      <Field label="District" value={district} onChangeText={setDistrict} error={fieldErrors.district} />
      <Field label="Street" value={street} onChangeText={setStreet} error={fieldErrors.street} />
      <Field label="Landmark (optional)" value={landmark} onChangeText={setLandmark} />
      <Field label="Postal code (optional)" value={postalCode} onChangeText={setPostalCode} />

      <PrimaryButton
        label="Save address & continue"
        loading={submitting}
        disabled={submitting}
        onPress={handleSubmit}
        style={styles.button}
      />
    </Card>
  );
}

function Field({
  label,
  value,
  onChangeText,
  error,
  placeholder,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  error?: string;
  placeholder?: string;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={[styles.input, error ? styles.inputError : null]}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.textSubtle}
        autoCapitalize="none"
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  title: { ...typography.title, fontSize: 16 },
  note: {
    marginTop: spacing.xs,
    marginBottom: spacing.md,
    ...typography.caption,
  },
  field: { marginBottom: spacing.md },
  label: {
    ...typography.label,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
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
  inputError: { borderColor: colors.error },
  error: { marginTop: spacing.xs, ...typography.caption, color: colors.error },
  button: { marginTop: spacing.sm, alignSelf: 'stretch' },
});
