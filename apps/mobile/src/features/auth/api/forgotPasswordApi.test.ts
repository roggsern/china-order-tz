import { requestPasswordReset, forgotPasswordRequestSchema } from './forgotPasswordApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
}));

import { apiClient } from '@/src/core/api';

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;

describe('forgotPasswordApi', () => {
  beforeEach(() => {
    mockPost.mockReset();
  });

  it('validates email before request', () => {
    expect(forgotPasswordRequestSchema.safeParse({ email: 'bad' }).success).toBe(
      false,
    );
    expect(
      forgotPasswordRequestSchema.safeParse({ email: 'user@example.com' }).success,
    ).toBe(true);
  });

  it('posts normalized email to /auth/forgot-password and maps neutral message', async () => {
    mockPost.mockResolvedValue({
      success: true,
      message:
        'If an account exists for that email, password reset instructions have been sent.',
    } as never);

    const result = await requestPasswordReset({ email: 'User@Example.com' });

    expect(mockPost).toHaveBeenCalledWith(
      '/auth/forgot-password',
      { email: 'user@example.com' },
      null,
    );
    expect(result.success).toBe(true);
    expect(result.message).toMatch(/if an account exists/i);
  });
});
