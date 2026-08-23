/**
 * In-process lock for non-idempotent customer actions (pay, place order, return).
 * Prevents double-tap duplicate submissions in the same JS turn.
 */
export type ExclusiveLock = {
  tryAcquire: () => boolean;
  release: () => void;
  isHeld: () => boolean;
};

export function createExclusiveLock(): ExclusiveLock {
  let held = false;
  return {
    tryAcquire() {
      if (held) return false;
      held = true;
      return true;
    },
    release() {
      held = false;
    },
    isHeld() {
      return held;
    },
  };
}

export async function runExclusive<T>(
  lock: ExclusiveLock,
  work: () => Promise<T>,
): Promise<T | 'busy'> {
  if (!lock.tryAcquire()) return 'busy';
  try {
    return await work();
  } finally {
    lock.release();
  }
}
