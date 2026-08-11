import { type ReactNode } from 'react';
import {
  ScrollView,
  StyleSheet,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import {
  SafeAreaView,
  type Edge,
} from 'react-native-safe-area-context';
import { colors, spacing } from '../theme';

type Props = {
  children: ReactNode;
  /** Apply horizontal content padding (default true). */
  padded?: boolean;
  /** Use ScrollView instead of a plain View. */
  scroll?: boolean;
  /** Safe-area edges; default bottom-only when a custom header owns the top inset. */
  edges?: Edge[];
  style?: StyleProp<ViewStyle>;
  contentStyle?: StyleProp<ViewStyle>;
  backgroundColor?: string;
};

/**
 * Consistent page chrome for tab/stack content under AppHeader.
 */
export function ScreenContainer({
  children,
  padded = true,
  scroll = false,
  edges = ['bottom'],
  style,
  contentStyle,
  backgroundColor = colors.background,
}: Props) {
  return (
    <SafeAreaView
      edges={edges}
      style={[styles.root, { backgroundColor }, style]}
    >
      {scroll ? (
        <ScrollView
          contentContainerStyle={[
            styles.scrollContent,
            padded ? styles.padded : null,
            contentStyle,
          ]}
          keyboardShouldPersistTaps="handled"
        >
          {children}
        </ScrollView>
      ) : (
        <View style={[styles.body, padded ? styles.padded : null, contentStyle]}>
          {children}
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  body: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
  },
  padded: {
    paddingHorizontal: spacing.lg,
  },
});
