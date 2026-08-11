import { TextInput, StyleSheet, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

type Props = {
  value: string;
  onChangeText: (value: string) => void;
  onSubmit: () => void;
  autoFocus?: boolean;
  placeholder?: string;
};

export function SearchInput({
  value,
  onChangeText,
  onSubmit,
  autoFocus = true,
  placeholder = 'Search products, brands, stores…',
}: Props) {
  return (
    <View style={styles.wrap}>
      <TextInput
        style={styles.input}
        value={value}
        onChangeText={onChangeText}
        onSubmitEditing={onSubmit}
        returnKeyType="search"
        autoFocus={autoFocus}
        autoCapitalize="none"
        autoCorrect={false}
        clearButtonMode="while-editing"
        placeholder={placeholder}
        placeholderTextColor={colors.textSubtle}
        accessibilityLabel="Search"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.md,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.xl,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    ...typography.bodyStrong,
    color: colors.text,
    backgroundColor: colors.backgroundMuted,
  },
});
