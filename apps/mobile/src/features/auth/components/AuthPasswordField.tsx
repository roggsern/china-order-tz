import { useState } from 'react';
import { Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '@/src/shared/theme';
import { AuthField, type AuthFieldProps } from './AuthField';

type Props = Omit<AuthFieldProps, 'secureTextEntry' | 'rightAdornment'> & {
  /** Login uses password autofill; register uses new-password. */
  isNewPassword?: boolean;
};

export function AuthPasswordField({ isNewPassword = false, ...field }: Props) {
  const [hidden, setHidden] = useState(true);

  return (
    <AuthField
      {...field}
      secureTextEntry={hidden}
      autoCapitalize="none"
      autoCorrect={false}
      autoComplete={isNewPassword ? 'new-password' : 'password'}
      textContentType={isNewPassword ? 'newPassword' : 'password'}
      rightAdornment={
        <Pressable
          onPress={() => setHidden((current) => !current)}
          accessibilityRole="button"
          accessibilityLabel={hidden ? 'Show password' : 'Hide password'}
          hitSlop={8}
          style={styles.toggle}
        >
          <Ionicons
            name={hidden ? 'eye-outline' : 'eye-off-outline'}
            size={22}
            color={colors.textMuted}
          />
        </Pressable>
      }
    />
  );
}

const styles = StyleSheet.create({
  toggle: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
