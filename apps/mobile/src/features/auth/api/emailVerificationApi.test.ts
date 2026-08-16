import {
  refreshAuthenticatedUser,
  resendEmailVerification,
} from './emailVerificationApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
    get: jest.fn(),
  },
}));

jest.mock('@/src/features/account/api/profileApi', () => ({
  fetchCurrentUser: jest.fn(),
}));

import { apiClient } from '@/src/core/api';
import { fetchCurrentUser } from '@/src/features/account/api/profileApi';

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;
const mockFetchCurrentUser = fetchCurrentUser as jest.MockedFunction<
  typeof fetchCurrentUser
>;

describe('emailVerificationApi', () => {
  beforeEach(() => {
    mockPost.mockReset();
    mockFetchCurrentUser.mockReset();
  });

  it('posts to /account/email/verify/resend and maps already_verified', async () => {
    mockPost.mockResolvedValue({
      success: true,
      message: 'A verification link has been sent to your email address.',
      already_verified: false,
    } as never);

    const result = await resendEmailVerification();

    expect(mockPost).toHaveBeenCalledWith('/account/email/verify/resend', {});
    expect(result.success).toBe(true);
    expect(result.alreadyVerified).toBe(false);
    expect(result.message).toMatch(/verification link/i);
  });

  it('refreshes authenticated user from GET /me mapping', async () => {
    mockFetchCurrentUser.mockResolvedValue({
      id: 'u1',
      name: 'Ada',
      email: 'ada@example.com',
      email_verified_at: '2026-08-16T10:00:00Z',
    });

    const user = await refreshAuthenticatedUser();
    expect(user.email).toBe('ada@example.com');
    expect(user.email_verified_at).toBe('2026-08-16T10:00:00Z');
  });
});
