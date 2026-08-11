import { ApiError, mapApiError, mapNetworkError } from '@/src/core/errors';

describe('mapApiError', () => {
  it('maps Contract v1 error envelope fields', () => {
    const error = mapApiError(422, {
      success: false,
      code: 'validation_failed',
      message: 'Invalid input',
      errors: { email: ['Required'] },
      request_id: 'req_123',
    });

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(422);
    expect(error.code).toBe('validation_failed');
    expect(error.message).toBe('Invalid input');
    expect(error.errors).toEqual({ email: ['Required'] });
    expect(error.requestId).toBe('req_123');
  });

  it('infers unauthenticated from HTTP 401 when code missing', () => {
    const error = mapApiError(401, { success: false, message: 'Unauthenticated' });
    expect(error.code).toBe('unauthenticated');
    expect(error.isUnauthenticated).toBe(true);
  });

  it('recognizes payment_failed and business_rule_violated', () => {
    expect(mapApiError(422, { code: 'payment_failed', message: 'Declined' }).code).toBe(
      'payment_failed',
    );
    expect(
      mapApiError(422, { code: 'business_rule_violated', message: 'Blocked' }).code,
    ).toBe('business_rule_violated');
  });

  it('falls back to status-derived code for unknown codes', () => {
    const error = mapApiError(404, { code: '', message: 'Missing' });
    expect(error.code).toBe('not_found');
  });
});

describe('mapNetworkError', () => {
  it('classifies offline failures as network_error', () => {
    const error = mapNetworkError(new Error('Network request failed'));
    expect(error.code).toBe('network_error');
    expect(error.status).toBe(0);
    expect(error.message).toBe(
      'No internet connection. Check your connection and try again.',
    );
  });

  it('classifies timeouts as timeout', () => {
    const error = mapNetworkError(new Error('Request timed out'));
    expect(error.code).toBe('timeout');
    expect(error.status).toBe(0);
  });
});
