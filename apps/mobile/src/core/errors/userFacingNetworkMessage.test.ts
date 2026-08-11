import { ApiError } from './apiError';
import {
  GENERIC_SERVER_MESSAGE,
  getSharedTransportErrorMessage,
  NETWORK_OFFLINE_MESSAGE,
  NETWORK_TIMEOUT_MESSAGE,
} from './userFacingNetworkMessage';
import { mapNetworkError } from './mapApiError';

describe('userFacingNetworkMessage', () => {
  it('maps offline / network_error', () => {
    const error = new ApiError({
      message: 'ignored',
      status: 0,
      code: 'network_error',
    });
    expect(getSharedTransportErrorMessage(error)).toBe(NETWORK_OFFLINE_MESSAGE);
  });

  it('maps timeout', () => {
    const error = new ApiError({
      message: 'ignored',
      status: 0,
      code: 'timeout',
    });
    expect(getSharedTransportErrorMessage(error)).toBe(NETWORK_TIMEOUT_MESSAGE);
  });

  it('maps server errors to generic copy', () => {
    const error = new ApiError({
      message: 'SQLException at line 12',
      status: 500,
      code: 'server_error',
    });
    expect(getSharedTransportErrorMessage(error)).toBe(GENERIC_SERVER_MESSAGE);
    expect(getSharedTransportErrorMessage(error)).not.toMatch(/SQL|stack|line/i);
  });

  it('mapNetworkError classifies timeout vs offline', () => {
    expect(mapNetworkError(new Error('Request timed out')).code).toBe('timeout');
    expect(mapNetworkError(new Error('Network request failed')).code).toBe(
      'network_error',
    );
  });
});
