import { StyleSheet, Text, View } from 'react-native';

type Props = {
  title: string;
  subtitle?: string | null;
};

export function SectionHeader({ title, subtitle }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>{title}</Text>
      {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 12,
    paddingHorizontal: 16,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111',
  },
  subtitle: {
    marginTop: 4,
    fontSize: 13,
    color: '#666',
  },
});
