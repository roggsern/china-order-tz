import { useEffect, useMemo } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useProductConfiguration } from '../hooks/useCatalogQueries';
import { pruneConfigurationSelections } from '../map/mapProduct';
import type {
  ConfigurationSelections,
  ProductConfiguration,
} from '../models/types';
import { getCatalogErrorMessage } from '../utils/catalogErrorMessage';
import { ConfigurationAttributePicker } from './ConfigurationAttributePicker';

type Props = {
  productKey: string;
  selections: ConfigurationSelections;
  onSelectionsChange: (next: ConfigurationSelections) => void;
  onConfigurationChange: (configuration: ProductConfiguration | null) => void;
  onStatusChange?: (status: {
    loading: boolean;
    error: boolean;
  }) => void;
};

export function ProductConfigurationSelector({
  productKey,
  selections,
  onSelectionsChange,
  onConfigurationChange,
  onStatusChange,
}: Props) {
  const query = useProductConfiguration(productKey, selections, true);
  // Keep previous attributes for picker UX, but treat any in-flight fetch as unknown
  // so ATC never uses a stale matchedConfigurationId / availability.
  const loading = query.isPending || query.isFetching;
  const error = query.isError && !query.isFetching;
  const configuration = query.data ?? null;

  useEffect(() => {
    if (loading) {
      // Invalidate previous server match while refetching selections.
      onConfigurationChange(
        configuration
          ? {
              ...configuration,
              matchedConfigurationId: null,
              matchedUnitPrice: null,
              isComplete: false,
              isPurchasable: undefined,
              isInStock: null,
              availabilityStatus: null,
            }
          : null,
      );
      return;
    }
    onConfigurationChange(configuration);
  }, [configuration, loading, onConfigurationChange]);

  useEffect(() => {
    onStatusChange?.({ loading, error });
  }, [loading, error, onStatusChange]);

  useEffect(() => {
    if (!configuration) return;
    const pruned = pruneConfigurationSelections(
      selections,
      configuration.allowedValueIds,
    );
    const same =
      Object.keys(pruned).length === Object.keys(selections).length &&
      Object.entries(pruned).every(([key, value]) => selections[key] === value);
    if (!same) {
      onSelectionsChange(pruned);
    }
  }, [configuration, onSelectionsChange, selections]);

  const selectableAttributes = useMemo(
    () =>
      (configuration?.attributes ?? []).filter(
        (attribute) => attribute.participatesInConfiguration,
      ),
    [configuration?.attributes],
  );

  if (query.isLoading && !configuration) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator color={colors.primary} />
        <Text style={styles.muted}>Loading options…</Text>
      </View>
    );
  }

  if (query.isError && !configuration) {
    return (
      <Text style={styles.error}>{getCatalogErrorMessage(query.error)}</Text>
    );
  }

  if (!configuration?.hasConfigurations) {
    return null;
  }

  return (
    <Card elevated={false} style={styles.wrap}>
      <Text style={styles.title}>Select options</Text>
      {selectableAttributes.map((attribute) => (
        <ConfigurationAttributePicker
          key={attribute.id}
          attribute={attribute}
          selectedValueId={selections[attribute.id] ?? null}
          allowedValueIds={configuration.allowedValueIds[attribute.id] ?? []}
          disabled={query.isFetching}
          onSelect={(valueId) => {
            onSelectionsChange({
              ...selections,
              [attribute.id]: valueId,
            });
          }}
        />
      ))}
      {!loading && !configuration.isComplete ? (
        <Text style={styles.hint}>Select all required options to continue.</Text>
      ) : null}
      {!loading &&
      !error &&
      configuration.isComplete &&
      configuration.matchedConfigurationId ? (
        <Badge label="Configuration matched" tone="success" style={styles.matched} />
      ) : null}
      {loading ? (
        <Text style={styles.hint}>Checking availability…</Text>
      ) : null}
      {error ? (
        <Text style={styles.error}>{getCatalogErrorMessage(query.error)}</Text>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  title: {
    ...typography.title,
    fontSize: 16,
    marginBottom: spacing.md,
  },
  centered: {
    marginTop: spacing.lg,
    alignItems: 'center',
    gap: spacing.sm,
  },
  muted: {
    ...typography.caption,
  },
  error: {
    marginTop: spacing.sm,
    ...typography.caption,
    color: colors.error,
  },
  hint: {
    marginTop: spacing.xs,
    ...typography.caption,
  },
  matched: {
    marginTop: spacing.sm,
    alignSelf: 'flex-start',
  },
});
