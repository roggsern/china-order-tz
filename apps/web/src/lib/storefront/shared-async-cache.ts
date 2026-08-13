/**
 * Tiny module-level async cache with in-flight dedupe.
 * Keys must encode audience/auth scope — callers are responsible.
 */

export type SharedAsyncCacheStats = {
  starts: number;
  joins: number;
  hits: number;
};

type Entry<T> = {
  expiresAt: number;
  value: T;
};

export function createSharedAsyncCache(options?: { ttlMs?: number }) {
  const ttlMs = options?.ttlMs ?? 60_000;
  const values = new Map<string, Entry<unknown>>();
  const inflight = new Map<string, Promise<unknown>>();
  const stats: SharedAsyncCacheStats = { starts: 0, joins: 0, hits: 0 };

  async function getOrFetch<T>(key: string, loader: () => Promise<T>): Promise<T> {
    const now = Date.now();
    const cached = values.get(key) as Entry<T> | undefined;
    if (cached && cached.expiresAt > now) {
      stats.hits += 1;
      return cached.value;
    }

    const pending = inflight.get(key) as Promise<T> | undefined;
    if (pending) {
      stats.joins += 1;
      return pending;
    }

    stats.starts += 1;
    const promise = loader()
      .then((value) => {
        values.set(key, { value, expiresAt: Date.now() + ttlMs });
        inflight.delete(key);
        return value;
      })
      .catch((error: unknown) => {
        inflight.delete(key);
        throw error;
      });

    inflight.set(key, promise);
    return promise;
  }

  function clear(key?: string): void {
    if (key === undefined) {
      values.clear();
      inflight.clear();
      return;
    }
    values.delete(key);
    inflight.delete(key);
  }

  function getStats(): SharedAsyncCacheStats {
    return { ...stats };
  }

  function resetStats(): void {
    stats.starts = 0;
    stats.joins = 0;
    stats.hits = 0;
  }

  return { getOrFetch, clear, getStats, resetStats };
}
