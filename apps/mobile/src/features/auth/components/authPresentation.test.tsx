import { useState } from 'react';
import {
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react-native';
import { Platform, StyleSheet } from 'react-native';
import fs from 'fs';
import path from 'path';
import appJson from '../../../../app.json';
import { openAccountWebPage } from '@/src/features/account/utils/accountWebLinks';
import { colors } from '@/src/shared/theme';
import { resolveBrandMarkLayout } from '@/src/shared/branding/BrandMark';
import { brandAssetPaths } from '@/src/shared/branding/assets';
import { SPLASH_VIEW_MARK_SIZE } from '@/src/shared/branding/splashPresentation';
import { PrimaryButton } from '@/src/shared/ui';
import { AUTH_BRAND_SIZE, AUTH_BRAND_VARIANT, AuthHeader } from './AuthHeader';
import { AuthLegalNote } from './AuthLegalNote';
import { AuthPasswordField } from './AuthPasswordField';
import { AuthShell } from './AuthShell';
import {
  AUTH_FORGOT_PASSWORD_HREF,
  AUTH_HOME_HREF,
  AUTH_LOGIN_HREF,
  AUTH_REGISTER_HREF,
} from './authRoutes';

jest.mock('@/src/features/account/utils/accountWebLinks', () => {
  const actual = jest.requireActual(
    '@/src/features/account/utils/accountWebLinks',
  ) as typeof import('@/src/features/account/utils/accountWebLinks');
  return {
    ...actual,
    openAccountWebPage: jest.fn().mockResolvedValue(undefined),
  };
});

const mockOpenAccountWebPage = openAccountWebPage as jest.MockedFunction<
  typeof openAccountWebPage
>;

function PasswordHarness() {
  const [value, setValue] = useState('Secret123!');
  return (
    <AuthPasswordField
      label="Password"
      value={value}
      onChangeText={setValue}
      accessibilityLabel="Password"
    />
  );
}

describe('auth navigation contracts', () => {
  it('keeps login, register, forgot-password, and home hrefs unchanged', () => {
    expect(AUTH_LOGIN_HREF).toBe('/(auth)/login');
    expect(AUTH_REGISTER_HREF).toBe('/(auth)/register');
    expect(AUTH_FORGOT_PASSWORD_HREF).toBe('/(auth)/forgot-password');
    expect(AUTH_HOME_HREF).toBe('/(app)/(tabs)/home');
  });
});

describe('auth branding', () => {
  it('uses the compact mark asset, not the splash-safe square lockup', () => {
    expect(AUTH_BRAND_VARIANT).toBe('mark');
    expect(AUTH_BRAND_SIZE).toBe(40);
    expect(AUTH_BRAND_SIZE).toBeLessThan(SPLASH_VIEW_MARK_SIZE);

    const mark = resolveBrandMarkLayout(AUTH_BRAND_VARIANT, AUTH_BRAND_SIZE);
    expect(mark.width).toBe(AUTH_BRAND_SIZE);
    expect(mark.height).toBe(AUTH_BRAND_SIZE);
    expect(mark.resizeMode).toBe('contain');

    const splash = resolveBrandMarkLayout('splash', SPLASH_VIEW_MARK_SIZE);
    expect(splash.width).toBe(SPLASH_VIEW_MARK_SIZE);
    expect(brandAssetPaths.logoMark).not.toBe(brandAssetPaths.splashBrandSafe);
  });

  it('renders CHINA ORDER TZ without splash-safe source in auth screens', async () => {
    await render(
      <AuthHeader title="Welcome back" subtitle="Sign in to your account." />,
    );

    expect(screen.getByLabelText('CHINA ORDER TZ')).toBeTruthy();
    expect(screen.getByText('CHINA ORDER TZ')).toBeTruthy();
    expect(screen.getByText('Welcome back')).toBeTruthy();

    const authUiFiles = [
      'app/(auth)/login.tsx',
      'app/(auth)/register.tsx',
      'app/(auth)/_layout.tsx',
      'src/features/auth/components/AuthHeader.tsx',
      'src/features/auth/components/AuthShell.tsx',
      'src/features/auth/components/ForgotPasswordScreen.tsx',
    ];

    for (const relative of authUiFiles) {
      const source = fs.readFileSync(
        path.join(__dirname, '../../../../', relative),
        'utf8',
      );
      expect(source).not.toContain('splash-brand-safe');
      expect(source).not.toContain('variant="splash"');
      expect(source).not.toContain("variant='splash'");
    }
  });

  it('preserves the app light-only appearance policy', () => {
    expect(appJson.expo.userInterfaceStyle).toBe('light');
    expect(colors.surfaceCream).toBe('#fff8ea');
  });
});

describe('AuthShell', () => {
  it('stays scrollable and keyboard-safe', async () => {
    await render(<AuthShell>{null}</AuthShell>);

    const scroll = screen.getByTestId('auth-scroll');
    expect(scroll.props.keyboardShouldPersistTaps).toBe('handled');
    expect(scroll.props.keyboardDismissMode).toBe('on-drag');
    const style = StyleSheet.flatten(scroll.props.contentContainerStyle);
    expect(style.flexGrow).toBe(1);
    expect(style.paddingBottom).toBe(48);
    expect(
      StyleSheet.flatten(screen.getByTestId('auth-column').props.style).maxWidth,
    ).toBe(440);

    const source = fs.readFileSync(
      path.join(__dirname, 'AuthShell.tsx'),
      'utf8',
    );
    expect(source).toContain('KeyboardAvoidingView');
    expect(source).toContain("Platform.OS === 'ios' ? 'padding' : 'height'");
    expect(source).toContain('keyboardVerticalOffset');
    expect(Platform.OS).toBeDefined();
  });
});

describe('AuthPasswordField', () => {
  it('toggles visibility without changing the value', async () => {
    await render(<PasswordHarness />);

    const input = screen.getByLabelText('Password');
    expect(input.props.secureTextEntry).toBe(true);
    expect(input.props.autoComplete).toBe('password');
    expect(input.props.textContentType).toBe('password');
    expect(screen.getByDisplayValue('Secret123!')).toBeTruthy();

    await fireEvent.press(screen.getByLabelText('Show password'));

    expect(screen.getByLabelText('Password').props.secureTextEntry).toBe(false);
    expect(screen.getByDisplayValue('Secret123!')).toBeTruthy();
    expect(screen.getByLabelText('Hide password')).toBeTruthy();
  });
});

describe('AuthLegalNote', () => {
  it('keeps Terms and Privacy Policy handoff links', async () => {
    mockOpenAccountWebPage.mockClear();
    await render(<AuthLegalNote />);

    expect(
      screen.getByText(/By creating an account you agree to our/i),
    ).toBeTruthy();

    await fireEvent.press(screen.getByText('Terms'));
    await fireEvent.press(screen.getByText('Privacy Policy'));

    await waitFor(() => {
      expect(mockOpenAccountWebPage).toHaveBeenCalledWith('/terms');
      expect(mockOpenAccountWebPage).toHaveBeenCalledWith('/privacy');
    });
  });
});

describe('PrimaryButton loading guard', () => {
  it('blocks duplicate submit while loading', async () => {
    const onPress = jest.fn();
    await render(
      <PrimaryButton
        label="Sign in"
        loading
        onPress={onPress}
        accessibilityLabel="Sign in"
      />,
    );

    await fireEvent.press(screen.getByLabelText('Sign in'));
    expect(onPress).not.toHaveBeenCalled();
  });
});
