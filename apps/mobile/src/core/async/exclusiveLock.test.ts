import { createExclusiveLock, runExclusive } from './exclusiveLock';
import { canSubmitInFlightAction } from './inFlightGuard';

describe('exclusiveLock', () => {
  it('double tap starts a payment action once', async () => {
    const lock = createExclusiveLock();
    let starts = 0;
    const start = async () => {
      starts += 1;
      await new Promise((resolve) => {
        setTimeout(resolve, 20);
      });
    };

    const [first, second] = await Promise.all([
      runExclusive(lock, start),
      runExclusive(lock, start),
    ]);

    expect(starts).toBe(1);
    expect(first).toBeUndefined();
    expect(second).toBe('busy');
  });

  it('prevents overlapping payment refresh', async () => {
    const lock = createExclusiveLock();
    let refreshes = 0;
    const refresh = async () => {
      refreshes += 1;
      await new Promise((resolve) => {
        setTimeout(resolve, 20);
      });
    };

    const [first, second] = await Promise.all([
      runExclusive(lock, refresh),
      runExclusive(lock, refresh),
    ]);

    expect(refreshes).toBe(1);
    expect(first).toBeUndefined();
    expect(second).toBe('busy');
  });

  it('releases after a network failure so a later user retry is possible', async () => {
    const lock = createExclusiveLock();
    let starts = 0;

    await expect(
      runExclusive(lock, async () => {
        starts += 1;
        throw new Error('Network request failed');
      }),
    ).rejects.toThrow(/Network request failed/);

    expect(lock.isHeld()).toBe(false);
    await runExclusive(lock, async () => {
      starts += 1;
    });
    expect(starts).toBe(2);
  });

  it('guards checkout, return, and receiving submissions while pending', () => {
    expect(canSubmitInFlightAction(true)).toBe(false);
    expect(canSubmitInFlightAction(false)).toBe(true);
  });
});
