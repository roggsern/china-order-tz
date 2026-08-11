import { createApiRequestSignal, DEFAULT_API_REQUEST_TIMEOUT_MS } from './client';
import { mapNetworkError } from '@/src/core/errors';

describe('createApiRequestSignal', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('aborts with timeout after deadline', () => {
    const { signal, cleanup } = createApiRequestSignal(1000);
    expect(signal.aborted).toBe(false);
    jest.advanceTimersByTime(1000);
    expect(signal.aborted).toBe(true);
    expect(mapNetworkError(signal.reason).code).toBe('timeout');
    cleanup();
  });

  it('normal request unaffected before deadline', () => {
    const { signal, cleanup } = createApiRequestSignal(DEFAULT_API_REQUEST_TIMEOUT_MS);
    jest.advanceTimersByTime(DEFAULT_API_REQUEST_TIMEOUT_MS - 1);
    expect(signal.aborted).toBe(false);
    cleanup();
    expect(signal.aborted).toBe(false);
  });

  it('propagates external abort', () => {
    const external = new AbortController();
    const { signal, cleanup } = createApiRequestSignal(30_000, external.signal);
    external.abort(Object.assign(new Error('Request timed out'), { name: 'TimeoutError' }));
    expect(signal.aborted).toBe(true);
    cleanup();
  });
});

describe('mapNetworkError timeout classification', () => {
  it('maps TimeoutError to timeout code', () => {
    const error = Object.assign(new Error('Request timed out'), { name: 'TimeoutError' });
    expect(mapNetworkError(error).code).toBe('timeout');
  });
});
