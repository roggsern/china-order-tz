import { ApiError } from '@/src/core/errors';
import { getAuthErrorMessage, getAuthFieldErrors } from './authErrorMessage';

describe('getAuthErrorMessage', () => {
  it('maps non-ApiError failures to customer-friendly copy', () => {
    const axiosLike = {
      name: 'AxiosError',
      message: 'Request failed with status code 500',
      isAxiosError: true,
      config: { url: '/login' },
    };

    const message = getAuthErrorMessage(axiosLike);
    expect(message).toBe('Something went wrong. Please try again.');
    expect(message).not.toMatch(/axios/i);
    expect(message).not.toContain('500');
    expect(message).not.toContain('/login');
  });

  it('prefers contract messages over status codes and route names', () => {
    expect(
      getAuthErrorMessage(
        new ApiError({
          message: 'Invalid email or password.',
          status: 422,
          code: 'invalid_credentials',
        }),
      ),
    ).toBe('Invalid email or password.');

    expect(
      getAuthErrorMessage(
        new ApiError({
          message: 'Please check your details.',
          status: 422,
          code: 'validation_failed',
          errors: { email: ['This email is already registered.'] },
        }),
      ),
    ).toBe('This email is already registered.');
  });
});

describe('getAuthFieldErrors', () => {
  it('returns field copy only for validation_failed', () => {
    expect(
      getAuthFieldErrors(
        new ApiError({
          message: 'Invalid credentials',
          status: 422,
          code: 'invalid_credentials',
        }),
      ),
    ).toEqual({});

    expect(
      getAuthFieldErrors(
        new ApiError({
          message: 'Please check your details.',
          status: 422,
          code: 'validation_failed',
          errors: { email: ['This email is already registered.'] },
        }),
      ),
    ).toEqual({ email: 'This email is already registered.' });
  });
});
