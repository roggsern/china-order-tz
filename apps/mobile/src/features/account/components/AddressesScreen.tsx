import { Alert, FlatList, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useAddressMutations, useCustomerAddresses } from '../hooks/useCustomerAddresses';
import type { CustomerAddress } from '../api/addressesApi';

export function AddressesScreen() {
  const query = useCustomerAddresses();
  const mutations = useAddressMutations();

  if (query.isLoading) {
    return (
      <ScreenContainer>
        <Text style={styles.loading}>Loading addresses…</Text>
      </ScreenContainer>
    );
  }

  if (query.isError) {
    return (
      <ScreenContainer>
        <EmptyState
          title="Addresses unavailable"
          message="Could not load your delivery addresses."
          actionLabel="Retry"
          onActionPress={() => void query.refetch()}
        />
      </ScreenContainer>
    );
  }

  const addresses = query.data?.addresses ?? [];

  async function onDelete(address: CustomerAddress) {
    Alert.alert(
      'Remove address?',
      `${address.recipientName} — ${address.street}`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: () => void mutations.remove.mutateAsync(address.id),
        },
      ],
    );
  }

  return (
    <ScreenContainer padded={false}>
      <FlatList
        contentContainerStyle={styles.content}
        data={addresses}
        keyExtractor={(item) => item.id}
        ListHeaderComponent={
          <View style={styles.header}>
            <Text style={styles.eyebrow}>Account</Text>
            <Text style={styles.heading}>Addresses</Text>
            <Text style={styles.subheading}>
              Delivery addresses from your CHINA ORDER TZ account.
            </Text>
            <PrimaryButton
              label="Add address"
              onPress={() => router.push('/(app)/account/address-form' as never)}
              style={styles.addBtn}
            />
          </View>
        }
        ListEmptyComponent={
          <EmptyState
            title="No addresses yet"
            message="Add a delivery address for faster checkout."
          />
        }
        renderItem={({ item }) => (
          <Card elevated style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.name}>{item.recipientName}</Text>
              {item.isDefault ? <Badge label="Default" tone="brand" /> : null}
            </View>
            {item.label ? <Text style={styles.meta}>{item.label}</Text> : null}
            <Text style={styles.body}>
              {item.street}
              {item.district ? `, ${item.district}` : ''}
            </Text>
            <Text style={styles.body}>
              {item.city}, {item.region}
              {item.postalCode ? ` ${item.postalCode}` : ''}
            </Text>
            <Text style={styles.meta}>{item.phone}</Text>
            <View style={styles.actions}>
              {!item.isDefault ? (
                <SecondaryButton
                  label="Set default"
                  onPress={() => void mutations.setDefault.mutateAsync(item.id)}
                  disabled={mutations.setDefault.isPending}
                  style={styles.actionBtn}
                />
              ) : null}
              <SecondaryButton
                label="Edit"
                onPress={() =>
                  router.push(`/(app)/account/address-form?id=${item.id}` as never)
                }
                style={styles.actionBtn}
              />
              <SecondaryButton
                label="Remove"
                onPress={() => void onDelete(item)}
                disabled={mutations.remove.isPending}
                style={styles.actionBtn}
              />
            </View>
          </Card>
        )}
      />
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  header: { marginBottom: spacing.lg },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  subheading: {
    ...typography.caption,
    marginTop: spacing.xs,
    marginBottom: spacing.md,
  },
  addBtn: { alignSelf: 'stretch' },
  loading: { ...typography.body, padding: spacing.lg },
  card: {
    marginBottom: spacing.md,
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    marginBottom: spacing.xs,
  },
  name: { ...typography.bodyStrong, flex: 1 },
  body: { ...typography.body, marginTop: spacing.xxs },
  meta: { ...typography.caption, marginTop: spacing.xxs },
  actions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
  actionBtn: { minWidth: 100 },
});
