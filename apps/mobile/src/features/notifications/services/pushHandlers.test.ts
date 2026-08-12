/* eslint-disable import/first -- jest.mock must hoist before imports under test */
const mockPush = jest.fn();
const mockGetLastNotificationResponseAsync = jest.fn();
const mockClearLastNotificationResponseAsync = jest.fn();

jest.mock('expo-router', () => ({
  router: {
    push: (...args: unknown[]) => mockPush(...args),
  },
}));

jest.mock('expo-notifications', () => ({
  getLastNotificationResponseAsync: (...args: unknown[]) =>
    mockGetLastNotificationResponseAsync(...args),
  clearLastNotificationResponseAsync: (...args: unknown[]) =>
    mockClearLastNotificationResponseAsync(...args),
}));

import { useAuthStore } from '@/src/core/auth';
import { resetPendingNotificationNavigationForTests } from '../utils/pendingNotificationNavigation';
import {
  consumeNotificationResponse,
  handleNotificationResponseNavigation,
  consumeLastNotificationResponseOnLaunch,
} from './pushHandlers';

const ORDER_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

function makeResponse(id: string, data: Record<string, unknown>) {
  return {
    notification: {
      request: {
        identifier: id,
        content: { data },
      },
    },
  } as never;
}

describe('pushHandlers', () => {
  beforeEach(() => {
    mockPush.mockReset();
    mockGetLastNotificationResponseAsync.mockReset();
    mockClearLastNotificationResponseAsync.mockReset();
    resetPendingNotificationNavigationForTests();
    useAuthStore.setState({
      status: 'authenticated',
      user: { id: 'u1', name: 'Ada', email: 'ada@example.com' },
      bootstrapStatus: 'complete',
    });
  });

  it('navigates on notification tap when authenticated', () => {
    const href = handleNotificationResponseNavigation(
      makeResponse('tap-1', {
        event_type: 'order_created',
        order_id: ORDER_ID,
      }),
    );
    expect(href).toBe(`/(app)/orders/${ORDER_ID}`);
    expect(mockPush).toHaveBeenCalledWith(`/(app)/orders/${ORDER_ID}`);
  });

  it('does not navigate merely from consume without handle (arrival path)', () => {
    const href = consumeNotificationResponse(
      makeResponse('arrive-only', {
        event_type: 'order_created',
        order_id: ORDER_ID,
      }),
    );
    expect(href).toBe(`/(app)/orders/${ORDER_ID}`);
    expect(mockPush).not.toHaveBeenCalled();
  });

  it('deduplicates duplicate notification responses', () => {
    const response = makeResponse('dup-1', {
      event_type: 'order_created',
      order_id: ORDER_ID,
    });
    expect(handleNotificationResponseNavigation(response)).toBe(
      `/(app)/orders/${ORDER_ID}`,
    );
    expect(handleNotificationResponseNavigation(response)).toBeNull();
    expect(mockPush).toHaveBeenCalledTimes(1);
  });

  it('queues returnTo when unauthenticated on cold start', async () => {
    useAuthStore.setState({
      status: 'unauthenticated',
      user: null,
      bootstrapStatus: 'complete',
    });
    mockGetLastNotificationResponseAsync.mockResolvedValue(
      makeResponse('cold-1', {
        event_type: 'order_created',
        order_id: ORDER_ID,
      }),
    );

    const href = await consumeLastNotificationResponseOnLaunch();
    expect(href).toBe(`/(app)/orders/${ORDER_ID}`);
    expect(mockPush).not.toHaveBeenCalled();
    expect(mockClearLastNotificationResponseAsync).toHaveBeenCalled();
  });

  it('routes unauthenticated live tap through login returnTo', () => {
    useAuthStore.setState({
      status: 'unauthenticated',
      user: null,
      bootstrapStatus: 'complete',
    });
    handleNotificationResponseNavigation(
      makeResponse('live-unauth', {
        event_type: 'order_created',
        order_id: ORDER_ID,
      }),
    );
    expect(mockPush).toHaveBeenCalledWith(
      `/(auth)/login?returnTo=${encodeURIComponent(`/(app)/orders/${ORDER_ID}`)}`,
    );
  });
});
