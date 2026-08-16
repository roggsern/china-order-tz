import { closeAccount } from './closeAccountApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
}));

import { apiClient } from '@/src/core/api';

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;

describe('closeAccountApi', () => {
  beforeEach(() => {
    mockPost.mockReset();
  });

  it('posts current password and acknowledge to /account/close', async () => {
    mockPost.mockResolvedValue({
      success: true,
      message: 'Your account has been closed.',
      requires_reauthentication: true,
      already_closed: false,
    } as never);

    const result = await closeAccount({
      current_password: 'password123',
      acknowledge: true,
    });

    expect(mockPost).toHaveBeenCalledWith('/account/close', {
      current_password: 'password123',
      acknowledge: true,
    });
    expect(result.success).toBe(true);
    expect(result.requiresReauthentication).toBe(true);
    expect(result.alreadyClosed).toBe(false);
  });
});
