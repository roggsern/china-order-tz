import { Link, type Href } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, spacing, typography } from '@/src/shared/theme';

type Props = {
  prompt: string;
  actionLabel: string;
  href: Href;
  disabled?: boolean;
};

export function AuthFooterLink({ prompt, actionLabel, href, disabled }: Props) {
  return (
    <View style={styles.row}>
      {prompt ? <Text style={styles.prompt}>{prompt} </Text> : null}
      <Link href={href} asChild>
        <Pressable
          disabled={disabled}
          accessibilityRole="link"
          accessibilityLabel={actionLabel}
        >
          <Text style={styles.action}>{actionLabel}</Text>
        </Pressable>
      </Link>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    marginTop: spacing.xl,
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    alignItems: 'center',
  },
  prompt: {
    ...typography.body,
    color: colors.textSecondary,
  },
  action: {
    ...typography.bodyStrong,
    color: colors.primaryPressed,
  },
});
