import * as Notifications from 'expo-notifications';

type PushListenerCleanup = () => void;

let generation = 0;
let detachCurrent: PushListenerCleanup | null = null;

export function arePushRuntimeListenersAttached(): boolean {
  return detachCurrent !== null;
}

/**
 * Attach received/response/token listeners once.
 * A newer attach supersedes the previous set so remounts cannot stack listeners.
 */
export function attachPushRuntimeListeners(input: {
  onReceived: () => void;
  onResponse: (response: Notifications.NotificationResponse) => void;
  onToken: () => void;
}): PushListenerCleanup {
  detachCurrent?.();

  const myGeneration = generation + 1;
  generation = myGeneration;

  const receivedSub = Notifications.addNotificationReceivedListener(() => {
    input.onReceived();
  });
  const responseSub = Notifications.addNotificationResponseReceivedListener(
    (response) => {
      input.onResponse(response);
    },
  );
  const tokenSub = Notifications.addPushTokenListener(() => {
    input.onToken();
  });

  const cleanup = () => {
    if (myGeneration !== generation) return;
    receivedSub.remove();
    responseSub.remove();
    tokenSub.remove();
    if (detachCurrent === cleanup) {
      detachCurrent = null;
    }
  };

  detachCurrent = cleanup;
  return cleanup;
}

/** Test helper */
export function resetPushRuntimeListenersForTests(): void {
  detachCurrent?.();
  generation = 0;
  detachCurrent = null;
}
