import {
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react-native';
import { ApiError } from '@/src/core/errors';
import LoginScreen from '@/app/(auth)/login';
import RegisterScreen from '@/app/(auth)/register';
import { requestPasswordReset } from '@/src/features/auth/api/forgotPasswordApi';
import {
  loginWithPassword,
  registerAccount,
} from '@/src/features/auth/services/authSession';
import { router, useLocalSearchParams } from 'expo-router';
import { ForgotPasswordScreen } from './ForgotPasswordScreen';
import {
  AUTH_FORGOT_PASSWORD_HREF,
  AUTH_HOME_HREF,
  AUTH_LOGIN_HREF,
  AUTH_REGISTER_HREF,
} from './authRoutes';

jest.mock('expo-router', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const React = require('react') as typeof import('react');
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const { View } = require('react-native') as typeof import('react-native');
  return {
    router: {
      replace: jest.fn(),
      push: jest.fn(),
    },
    useLocalSearchParams: jest.fn(() => ({})),
    Link: ({
      href,
      children,
    }: {
      href: string;
      children: React.ReactNode;
    }) =>
      React.createElement(
        View,
        { testID: `href:${String(href)}` },
        children,
      ),
  };
});

jest.mock('@/src/features/auth/services/authSession', () => ({
  loginWithPassword: jest.fn(),
  registerAccount: jest.fn(),
  logout: jest.fn(),
}));

jest.mock('@/src/features/auth/api/forgotPasswordApi', () => {
  const actual = jest.requireActual(
    '@/src/features/auth/api/forgotPasswordApi',
  ) as typeof import('@/src/features/auth/api/forgotPasswordApi');
  return {
    ...actual,
    requestPasswordReset: jest.fn(),
  };
});

jest.mock('@/src/features/account/utils/accountWebLinks', () => {
  const actual = jest.requireActual(
    '@/src/features/account/utils/accountWebLinks',
  ) as typeof import('@/src/features/account/utils/accountWebLinks');
  return {
    ...actual,
    openAccountWebPage: jest.fn().mockResolvedValue(undefined),
  };
});

const mockLoginWithPassword = loginWithPassword as jest.Mock;
const mockRegisterAccount = registerAccount as jest.Mock;
const mockRequestPasswordReset = requestPasswordReset as jest.Mock;
const mockReplace = router.replace as jest.Mock;
const mockParams = useLocalSearchParams as jest.Mock;

async function fillLogin() {
  await fireEvent.changeText(
    screen.getByLabelText('Email'),
    'ada@example.com',
  );
  await fireEvent.changeText(
    screen.getByLabelText('Password'),
    'Password123!',
  );
}

describe('Login screen', () => {
  beforeEach(() => {
    mockLoginWithPassword.mockReset();
    mockReplace.mockReset();
    mockParams.mockReturnValue({});
  });

  it('maps fields to the same login request payload', async () => {
    mockLoginWithPassword.mockResolvedValue({ id: 'usr_1' });
    await render(<LoginScreen />);

    expect(screen.getByLabelText('Email').props.keyboardType).toBe(
      'email-address',
    );
    expect(screen.getByLabelText('Email').props.autoCapitalize).toBe('none');
    expect(screen.getByLabelText('Email').props.autoComplete).toBe('email');
    expect(screen.getByLabelText('Email').props.textContentType).toBe(
      'emailAddress',
    );
    expect(screen.getByLabelText('Password').props.autoComplete).toBe(
      'password',
    );
    expect(screen.getByLabelText('Password').props.textContentType).toBe(
      'password',
    );

    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(mockLoginWithPassword).toHaveBeenCalledWith({
        email: 'ada@example.com',
        password: 'Password123!',
      });
    });
    expect(mockReplace).toHaveBeenCalledWith(AUTH_HOME_HREF);
  });

  it('keeps login validation unchanged and does not submit', async () => {
    await render(<LoginScreen />);
    await fireEvent.press(screen.getByLabelText('Sign in'));

    expect(screen.getByText('Enter a valid email')).toBeTruthy();
    expect(screen.getByText('Password is required')).toBeTruthy();
    expect(mockLoginWithPassword).not.toHaveBeenCalled();
  });

  it('keeps forgot-password and create-account navigation unchanged', async () => {
    await render(<LoginScreen />);

    expect(screen.getByTestId(`href:${AUTH_FORGOT_PASSWORD_HREF}`)).toBeTruthy();
    expect(screen.getByLabelText('Forgot password?')).toBeTruthy();
    expect(screen.getByText('New to CHINA ORDER TZ?')).toBeTruthy();
    expect(screen.getByTestId(`href:${AUTH_REGISTER_HREF}`)).toBeTruthy();
    expect(screen.getByLabelText('Create account')).toBeTruthy();
  });

  it('preserves sanitized returnTo after sign-in', async () => {
    mockParams.mockReturnValue({ returnTo: '/(app)/checkout' });
    mockLoginWithPassword.mockResolvedValue({ id: 'usr_1' });
    await render(<LoginScreen />);

    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/(app)/checkout');
    });
    expect(
      screen.getByTestId(
        `href:/(auth)/register?returnTo=${encodeURIComponent('/(app)/checkout')}`,
      ),
    ).toBeTruthy();
  });

  it('ignores unsafe returnTo and goes home', async () => {
    mockParams.mockReturnValue({ returnTo: 'https://evil.example' });
    mockLoginWithPassword.mockResolvedValue({ id: 'usr_1' });
    await render(<LoginScreen />);

    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith(AUTH_HOME_HREF);
    });
  });

  it('blocks duplicate submit while loading', async () => {
    let resolveLogin: ((value: unknown) => void) | undefined;
    mockLoginWithPassword.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveLogin = resolve;
        }),
    );

    await render(<LoginScreen />);
    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(mockLoginWithPassword).toHaveBeenCalledTimes(1);
    });

    await fireEvent.press(screen.getByLabelText('Sign in'));
    expect(mockLoginWithPassword).toHaveBeenCalledTimes(1);
    resolveLogin?.({ id: 'usr_1' });
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith(AUTH_HOME_HREF);
    });
  });

  it('shows a customer-friendly auth error without Axios or route leakage', async () => {
    mockLoginWithPassword.mockRejectedValue({
      name: 'AxiosError',
      message: 'Request failed with status code 500',
      config: { url: '/login' },
    });

    await render(<LoginScreen />);
    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(
        screen.getByText('Something went wrong. Please try again.'),
      ).toBeTruthy();
    });
    await waitFor(() => {
      expect(screen.getByText('Sign in')).toBeTruthy();
    });
    expect(screen.queryByText(/axios/i)).toBeNull();
    expect(screen.queryByText('/login')).toBeNull();
    expect(screen.queryByText('500')).toBeNull();
  });

  it('shows mapped invalid-credential copy', async () => {
    mockLoginWithPassword.mockRejectedValue(
      new ApiError({
        message: 'Invalid email or password.',
        status: 422,
        code: 'invalid_credentials',
      }),
    );

    await render(<LoginScreen />);
    await fillLogin();
    await fireEvent.press(screen.getByLabelText('Sign in'));

    await waitFor(() => {
      expect(screen.getByText('Invalid email or password.')).toBeTruthy();
    });
    await waitFor(() => {
      expect(screen.getByText('Sign in')).toBeTruthy();
    });
  });
});

describe('Register screen', () => {
  beforeEach(() => {
    mockRegisterAccount.mockReset();
    mockReplace.mockReset();
    mockParams.mockReturnValue({});
  });

  it('maps fields to the same register request payload', async () => {
    mockRegisterAccount.mockResolvedValue({ id: 'usr_1' });
    await render(<RegisterScreen />);

    expect(screen.getByLabelText('Email').props.keyboardType).toBe(
      'email-address',
    );
    expect(screen.getByLabelText('Phone (optional)').props.keyboardType).toBe(
      'phone-pad',
    );
    expect(screen.getByLabelText('Password').props.autoComplete).toBe(
      'new-password',
    );
    expect(screen.getByLabelText('Password').props.textContentType).toBe(
      'newPassword',
    );
    expect(screen.getByText('Optional. Used for order updates.')).toBeTruthy();
    expect(screen.getByText('Use at least 8 characters.')).toBeTruthy();
    expect(screen.getByText('Terms')).toBeTruthy();
    expect(screen.getByText('Privacy Policy')).toBeTruthy();

    await fireEvent.changeText(screen.getByLabelText('Name'), 'Ada Lovelace');
    await fireEvent.changeText(
      screen.getByLabelText('Email'),
      'ada@example.com',
    );
    await fireEvent.changeText(
      screen.getByLabelText('Phone (optional)'),
      '0712345678',
    );
    await fireEvent.changeText(
      screen.getByLabelText('Password'),
      'Password123!',
    );
    await fireEvent.changeText(
      screen.getByLabelText('Confirm password'),
      'Password123!',
    );
    await fireEvent.press(screen.getByLabelText('Create account'));

    await waitFor(() => {
      expect(mockRegisterAccount).toHaveBeenCalledWith({
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        phone: '0712345678',
        password: 'Password123!',
        password_confirmation: 'Password123!',
      });
    });
  });

  it('omits blank phone from the parsed register payload', async () => {
    mockRegisterAccount.mockResolvedValue({ id: 'usr_1' });
    await render(<RegisterScreen />);

    await fireEvent.changeText(screen.getByLabelText('Name'), 'Ada');
    await fireEvent.changeText(
      screen.getByLabelText('Email'),
      'ada@example.com',
    );
    await fireEvent.changeText(
      screen.getByLabelText('Password'),
      'Password123!',
    );
    await fireEvent.changeText(
      screen.getByLabelText('Confirm password'),
      'Password123!',
    );
    await fireEvent.press(screen.getByLabelText('Create account'));

    await waitFor(() => {
      expect(mockRegisterAccount).toHaveBeenCalledTimes(1);
    });
    expect(mockRegisterAccount.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({
        name: 'Ada',
        email: 'ada@example.com',
        password: 'Password123!',
        password_confirmation: 'Password123!',
      }),
    );
    expect(mockRegisterAccount.mock.calls[0]?.[0].phone).toBeUndefined();
  });

  it('keeps register validation unchanged', async () => {
    await render(<RegisterScreen />);
    await fireEvent.press(screen.getByLabelText('Create account'));

    expect(screen.getByText('Name is required')).toBeTruthy();
    expect(screen.getByText('Enter a valid email')).toBeTruthy();
    expect(screen.getByText('Password must be at least 8 characters')).toBeTruthy();
    expect(screen.getByText('Confirm your password')).toBeTruthy();
    expect(mockRegisterAccount).not.toHaveBeenCalled();
  });

  it('keeps sign-in navigation unchanged and preserves returnTo', async () => {
    mockParams.mockReturnValue({ returnTo: '/(app)/checkout' });
    await render(<RegisterScreen />);

    expect(screen.getByText('Already have an account?')).toBeTruthy();
    expect(screen.getByLabelText('Sign in')).toBeTruthy();
    expect(
      screen.getByTestId(
        `href:/(auth)/login?returnTo=${encodeURIComponent('/(app)/checkout')}`,
      ),
    ).toBeTruthy();
  });

  it('defaults sign-in href to the login route', async () => {
    await render(<RegisterScreen />);
    expect(screen.getByTestId(`href:${AUTH_LOGIN_HREF}`)).toBeTruthy();
  });
});

describe('Forgot password screen', () => {
  beforeEach(() => {
    mockRequestPasswordReset.mockReset();
    mockReplace.mockReset();
  });

  it('keeps back-to-sign-in navigation on the login route', async () => {
    mockRequestPasswordReset.mockResolvedValue({
      success: true,
      message:
        'If an account exists for that email, password reset instructions have been sent.',
    });

    await render(<ForgotPasswordScreen />);

    expect(screen.getByTestId(`href:${AUTH_LOGIN_HREF}`)).toBeTruthy();
    await fireEvent.changeText(
      screen.getByLabelText('Email address'),
      'ada@example.com',
    );
    await fireEvent.press(
      screen.getByLabelText('Send password reset instructions'),
    );

    await waitFor(() => {
      expect(mockRequestPasswordReset).toHaveBeenCalledWith({
        email: 'ada@example.com',
      });
      expect(
        screen.getByText(
          /If an account exists for that email, password reset instructions have been sent./i,
        ),
      ).toBeTruthy();
    });

    await fireEvent.press(screen.getByLabelText('Back to sign in'));
    expect(mockReplace).toHaveBeenCalledWith(AUTH_LOGIN_HREF);
    expect(screen.getByText(/chinaordertz.com\/reset-password/i)).toBeTruthy();
  });
});
