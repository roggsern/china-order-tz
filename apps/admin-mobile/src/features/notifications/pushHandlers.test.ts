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

import { useAdminAuthStore } from '@/src/core/auth';
import { resetPendingNotificationNavigationForTests } from './pendingNotificationNavigation';
import {
  consumeLastNotificationResponseOnLaunch,
  consumeNotificationResponse,
  handleNotificationResponseNavigation,
} from './pushHandlers';

const ORDER_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const TICKET_ID = 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee';

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
    useAdminAuthStore.setState({
      status: 'authenticated',
      admin: {
        id: 'a1',
        name: 'Admin',
        email: 'admin@test.com',
        phone: null,
        is_super_admin: false,
        is_active: true,
        permissions: [],
        role: null,
      },
      bootstrapStatus: 'ready',
    });
  });

  it('navigates on notification tap when authenticated', () => {
    const href = handleNotificationResponseNavigation(
      makeResponse('tap-1', {
        destination: 'admin.order_detail',
        order_id: ORDER_ID,
      }),
    );
    expect(href).toBe(`/(app)/(tabs)/orders/${ORDER_ID}`);
    expect(mockPush).toHaveBeenCalledWith(`/(app)/(tabs)/orders/${ORDER_ID}`);
  });

  it('falls back to dashboard for unknown destination', () => {
    const href = handleNotificationResponseNavigation(
      makeResponse('unknown-1', { destination: 'admin.unknown' }),
    );
    expect(href).toBe('/(app)/(tabs)/dashboard');
    expect(mockPush).toHaveBeenCalledWith('/(app)/(tabs)/dashboard');
  });

  it('routes support ticket detail', () => {
    const href = handleNotificationResponseNavigation(
      makeResponse('support-1', {
        destination: 'admin.support_ticket',
        ticket_id: TICKET_ID,
      }),
    );
    expect(href).toBe(`/(app)/(tabs)/support/${TICKET_ID}`);
  });

  it('does not navigate from consume alone', () => {
    const href = consumeNotificationResponse(
      makeResponse('arrive-only', {
        destination: 'admin.orders',
      }),
    );
    expect(href).toBe('/(app)/(tabs)/orders');
    expect(mockPush).not.toHaveBeenCalled();
  });

  it('deduplicates duplicate notification responses', () => {
    const response = makeResponse('dup-1', {
      destination: 'admin.orders',
    });
    expect(handleNotificationResponseNavigation(response)).toBe('/(app)/(tabs)/orders');
    expect(handleNotificationResponseNavigation(response)).toBeNull();
    expect(mockPush).toHaveBeenCalledTimes(1);
  });

  it('queues href when unauthenticated on cold start', async () => {
    useAdminAuthStore.setState({
      status: 'unauthenticated',
      admin: null,
      bootstrapStatus: 'ready',
    });
    mockGetLastNotificationResponseAsync.mockResolvedValue(
      makeResponse('cold-1', {
        destination: 'admin.order_detail',
        order_id: ORDER_ID,
      }),
    );

    const href = await consumeLastNotificationResponseOnLaunch();
    expect(href).toBe(`/(app)/(tabs)/orders/${ORDER_ID}`);
    expect(mockPush).not.toHaveBeenCalled();
    expect(mockClearLastNotificationResponseAsync).toHaveBeenCalled();
  });
});
