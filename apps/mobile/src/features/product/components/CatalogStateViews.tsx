import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { getCatalogErrorMessage } from '../utils/catalogErrorMessage';

type LoadingProps = { label?: string };

export function CatalogLoadingState({ label = 'Loading…' }: LoadingProps) {
  return (
    <View style={styles.centered}>
      <ActivityIndicator size="large" color="#0a7ea4" />
      <Text style={styles.muted}>{label}</Text>
    </View>
  );
}

type EmptyAction = {
  label: string;
  onPress: () => void;
  primary?: boolean;
};

type EmptyProps = {
  title: string;
  message?: string;
  actions?: EmptyAction[];
};

export function CatalogEmptyState({ title, message, actions }: EmptyProps) {
  return (
    <View style={styles.centered}>
      <Text style={styles.title}>{title}</Text>
      {message ? <Text style={styles.muted}>{message}</Text> : null}
      {actions?.length ? (
        <View style={styles.actions}>
          {actions.map((action) => (
            <Pressable
              key={action.label}
              style={action.primary ? styles.primaryButton : styles.secondaryButton}
              onPress={action.onPress}
            >
              <Text
                style={
                  action.primary ? styles.primaryButtonText : styles.secondaryButtonText
                }
              >
                {action.label}
              </Text>
            </Pressable>
          ))}
        </View>
      ) : null}
    </View>
  );
}

type ErrorProps = {
  error: unknown;
  onRetry?: () => void;
};

export function CatalogErrorState({ error, onRetry }: ErrorProps) {
  return (
    <View style={styles.centered}>
      <Text style={styles.title}>Something went wrong</Text>
      <Text style={styles.muted}>{getCatalogErrorMessage(error)}</Text>
      {onRetry ? (
        <Pressable style={styles.primaryButton} onPress={onRetry}>
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  muted: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  actions: {
    marginTop: 16,
    gap: 10,
    alignItems: 'center',
    width: '100%',
  },
  primaryButton: {
    marginTop: 8,
    backgroundColor: '#0a7ea4',
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 8,
    minWidth: 160,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  secondaryButton: {
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#0a7ea4',
    minWidth: 160,
    alignItems: 'center',
  },
  secondaryButtonText: {
    color: '#0a7ea4',
    fontWeight: '600',
  },
});
