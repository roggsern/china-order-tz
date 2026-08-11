import { StyleSheet } from 'react-native';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors } from '@/src/shared/theme';
import { getCatalogErrorMessage } from '../utils/catalogErrorMessage';

type LoadingProps = { label?: string };

export function CatalogLoadingState({ label = 'Loading…' }: LoadingProps) {
  return <ScreenLoadingState label={label} />;
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

/** Maps catalog empty actions onto shared EmptyState (primary + optional secondary). */
export function CatalogEmptyState({ title, message, actions }: EmptyProps) {
  const primary =
    actions?.find((action) => action.primary) ??
    (actions?.length === 1 ? actions[0] : undefined);
  const secondary = actions?.find((action) => action !== primary);

  return (
    <EmptyState
      title={title}
      message={message}
      actionLabel={primary?.label}
      onActionPress={primary?.onPress}
      secondaryLabel={secondary?.label}
      onSecondaryPress={secondary?.onPress}
      style={styles.fill}
    />
  );
}

type ErrorProps = {
  error: unknown;
  onRetry?: () => void;
};

export function CatalogErrorState({ error, onRetry }: ErrorProps) {
  return (
    <EmptyState
      title="Something went wrong"
      message={getCatalogErrorMessage(error)}
      actionLabel={onRetry ? 'Retry' : undefined}
      onActionPress={onRetry}
      style={styles.fill}
    />
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
    backgroundColor: colors.background,
  },
});
