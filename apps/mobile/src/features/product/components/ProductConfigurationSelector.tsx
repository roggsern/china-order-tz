import { useEffect, useMemo } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { useProductConfiguration } from '../hooks/useCatalogQueries';
import {
  pruneConfigurationSelections,
} from '../map/mapProduct';
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
        <ActivityIndicator color="#0a7ea4" />
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
    <View style={styles.wrap}>
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
      {!loading && !error && configuration.isComplete && configuration.matchedConfigurationId ? (
        <Text style={styles.matched}>Configuration matched</Text>
      ) : null}
      {loading ? (
        <Text style={styles.hint}>Checking availability...</Text>
      ) : null}
      {error ? (
        <Text style={styles.error}>{getCatalogErrorMessage(query.error)}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: 16,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 10,
  },
  centered: {
    marginTop: 16,
    alignItems: 'center',
    gap: 8,
  },
  muted: {
    color: '#666',
    fontSize: 13,
  },
  error: {
    marginTop: 16,
    color: '#b00020',
    fontSize: 13,
  },
  hint: {
    marginTop: 4,
    fontSize: 13,
    color: '#666',
  },
  matched: {
    marginTop: 4,
    fontSize: 13,
    color: '#0a7ea4',
    fontWeight: '600',
  },
});
