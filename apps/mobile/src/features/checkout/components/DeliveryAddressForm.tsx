import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
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
    <View style={styles.wrap}>
      <Text style={styles.title}>Delivery address</Text>
      <Text style={styles.note}>
        Customer address is required for checkout. It is not used as a delivery engine.
      </Text>

      <Field label="Recipient" value={recipientName} onChangeText={setRecipientName} error={fieldErrors.recipientName} />
      <Field label="Phone (E.164)" value={phone} onChangeText={setPhone} error={fieldErrors.phone} placeholder="+255..." />
      <Field label="Country" value={country} onChangeText={setCountry} error={fieldErrors.country} />
      <Field label="Region" value={region} onChangeText={setRegion} error={fieldErrors.region} />
      <Field label="City" value={city} onChangeText={setCity} error={fieldErrors.city} />
      <Field label="District" value={district} onChangeText={setDistrict} error={fieldErrors.district} />
      <Field label="Street" value={street} onChangeText={setStreet} error={fieldErrors.street} />
      <Field label="Landmark (optional)" value={landmark} onChangeText={setLandmark} />
      <Field label="Postal code (optional)" value={postalCode} onChangeText={setPostalCode} />

      <Pressable
        style={[styles.button, submitting ? styles.disabled : null]}
        disabled={submitting}
        onPress={handleSubmit}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Save address & continue</Text>
        )}
      </Pressable>
    </View>
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
        placeholderTextColor="#999"
        autoCapitalize="none"
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 12 },
  title: { fontSize: 16, fontWeight: '700', color: '#222' },
  note: { marginTop: 4, marginBottom: 12, fontSize: 12, color: '#666', lineHeight: 17 },
  field: { marginBottom: 10 },
  label: { fontSize: 13, fontWeight: '600', color: '#444', marginBottom: 4 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: '#222',
    backgroundColor: '#fff',
  },
  inputError: { borderColor: '#b00020' },
  error: { marginTop: 4, color: '#b00020', fontSize: 12 },
  button: {
    marginTop: 8,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  disabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
