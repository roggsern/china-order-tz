import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/src/core/errors';
import {
  MAX_TRANSIENT_READ_RETRIES,
  shouldRetryMutation,
  shouldRetryTransientRead,
} from './queryRetryPolicy';

function timeoutError() {
  return new ApiError({
    message: 'Request timed out',
    status: 0,
    code: 'timeout',
  });
}

function authError() {
  return new ApiError({
    message: 'Unauthenticated',
    status: 401,
    code: 'unauthenticated',
  });
}

describe('queryRetryPolicy', () => {
  it('retries a read timeout once and maps it as recoverable', () => {
    expect(shouldRetryTransientRead(timeoutError(), 0)).toBe(true);
    expect(shouldRetryTransientRead(timeoutError(), MAX_TRANSIENT_READ_RETRIES)).toBe(
      false,
    );
  });

  it('never automatically retries mutations', () => {
    expect(shouldRetryMutation()).toBe(false);
  });

  it('bounds transient read retries on the QueryClient', async () => {
    const client = new QueryClient({
      defaultOptions: {
        queries: {
          retry: (count, error) => shouldRetryTransientRead(error, count),
          retryDelay: 0,
          gcTime: 0,
        },
        mutations: { gcTime: 0, retry: 0 },
      },
    });
    let attempts = 0;

    await client
      .fetchQuery({
        queryKey: ['catalog', 'wave6-timeout'],
        queryFn: async () => {
          attempts += 1;
          throw timeoutError();
        },
        gcTime: 0,
      })
      .catch(() => undefined);

    expect(attempts).toBe(MAX_TRANSIENT_READ_RETRIES + 1);
    client.clear();
  });

  it('does not retry 401 on a protected query', async () => {
    const client = new QueryClient({
      defaultOptions: {
        queries: {
          retry: (count, error) => shouldRetryTransientRead(error, count),
          retryDelay: 0,
          gcTime: 0,
        },
        mutations: { gcTime: 0, retry: 0 },
      },
    });
    let attempts = 0;

    await client
      .fetchQuery({
        queryKey: ['orders', 'wave6-401'],
        queryFn: async () => {
          attempts += 1;
          throw authError();
        },
        gcTime: 0,
      })
      .catch(() => undefined);

    expect(attempts).toBe(1);
    expect(shouldRetryTransientRead(authError(), 0)).toBe(false);
    client.clear();
  });

  it('does not auto-repeat a mutation timeout', () => {
    expect(shouldRetryMutation()).toBe(false);
    expect(shouldRetryTransientRead(timeoutError(), 0)).toBe(true);
  });
});
