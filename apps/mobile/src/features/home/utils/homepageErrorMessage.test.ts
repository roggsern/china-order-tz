import { ApiError } from '@/src/core/errors';
import {
  GENERIC_SERVER_MESSAGE,
  NETWORK_OFFLINE_MESSAGE,
} from '@/src/core/errors/userFacingNetworkMessage';
import { getHomepageErrorMessage } from './homepageErrorMessage';

describe('getHomepageErrorMessage', () => {
  it('uses shared transport mapping for offline errors', () => {
    expect(
      getHomepageErrorMessage(
        new ApiError({
          message: 'Network request failed',
          status: 0,
          code: 'network_error',
        }),
      ),
    ).toBe(NETWORK_OFFLINE_MESSAGE);
  });

  it('uses shared server fallback for server_error', () => {
    expect(
      getHomepageErrorMessage(
        new ApiError({ message: '', status: 500, code: 'server_error' }),
      ),
    ).toBe(GENERIC_SERVER_MESSAGE);
  });

  it('handles maintenance_mode and unauthenticated', () => {
    expect(
      getHomepageErrorMessage(
        new ApiError({ message: 'Maint', status: 503, code: 'maintenance_mode' }),
      ),
    ).toBe('Maint');

    expect(
      getHomepageErrorMessage(
        new ApiError({ message: '', status: 401, code: 'unauthenticated' }),
      ),
    ).toMatch(/sign in/i);
  });
});
