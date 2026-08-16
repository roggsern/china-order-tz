import { secureTokenStorage } from '@/src/core/storage';

import { apiRequest, setApiTokenReader, setUnauthorizedHandler } from './client';

describe('apiRequest 401 handling', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    setApiTokenReader(async () => 'token');
    setUnauthorizedHandler(null);
    global.fetch = jest.fn();
  });

  it('clears token and invokes unauthorized handler on 401', async () => {
    const handler = jest.fn();
    setUnauthorizedHandler(handler);
    const clearSpy = jest.spyOn(secureTokenStorage, 'clearToken').mockResolvedValue();

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: false,
      status: 401,
      text: async () => JSON.stringify({ success: false, code: 'unauthenticated', message: 'Unauthenticated' }),
    });

    await expect(apiRequest({ path: '/admin/me' })).rejects.toMatchObject({ status: 401 });
    expect(clearSpy).toHaveBeenCalled();
    expect(handler).toHaveBeenCalled();
  });
});
