import {
  arePushRuntimeListenersAttached,
  attachPushRuntimeListeners,
  resetPushRuntimeListenersForTests,
} from './pushListeners';

const mockRemove = jest.fn();
const mockAddReceived = jest.fn((_listener?: unknown) => ({ remove: mockRemove }));
const mockAddResponse = jest.fn((_listener?: unknown) => ({ remove: mockRemove }));
const mockAddToken = jest.fn((_listener?: unknown) => ({ remove: mockRemove }));

jest.mock('expo-notifications', () => ({
  addNotificationReceivedListener: (listener: unknown) => mockAddReceived(listener),
  addNotificationResponseReceivedListener: (listener: unknown) =>
    mockAddResponse(listener),
  addPushTokenListener: (listener: unknown) => mockAddToken(listener),
}));

describe('pushListeners', () => {
  beforeEach(() => {
    resetPushRuntimeListenersForTests();
    mockRemove.mockClear();
    mockAddReceived.mockClear();
    mockAddResponse.mockClear();
    mockAddToken.mockClear();
  });

  afterEach(() => {
    resetPushRuntimeListenersForTests();
  });

  it('prevents stacked duplicate listener registration', () => {
    const first = attachPushRuntimeListeners({
      onReceived: () => undefined,
      onResponse: () => undefined,
      onToken: () => undefined,
    });
    expect(arePushRuntimeListenersAttached()).toBe(true);

    attachPushRuntimeListeners({
      onReceived: () => undefined,
      onResponse: () => undefined,
      onToken: () => undefined,
    });

    expect(mockAddReceived).toHaveBeenCalledTimes(2);
    expect(mockAddResponse).toHaveBeenCalledTimes(2);
    expect(mockAddToken).toHaveBeenCalledTimes(2);
    expect(mockRemove).toHaveBeenCalledTimes(3);

    first();
    expect(arePushRuntimeListenersAttached()).toBe(true);
  });

  it('logout-safe cleanup detaches the current listener set', () => {
    const cleanup = attachPushRuntimeListeners({
      onReceived: () => undefined,
      onResponse: () => undefined,
      onToken: () => undefined,
    });
    cleanup();
    expect(arePushRuntimeListenersAttached()).toBe(false);
    expect(mockRemove).toHaveBeenCalledTimes(3);
  });
});
