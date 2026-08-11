import { TextInput, StyleSheet, View } from 'react-native';

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
  placeholder = 'Search products…',
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
        placeholderTextColor="#999"
        accessibilityLabel="Search"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: '#222',
    backgroundColor: '#fafafa',
  },
});
