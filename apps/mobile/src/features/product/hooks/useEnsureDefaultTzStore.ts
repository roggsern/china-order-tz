import { useEffect } from 'react';
import { useJourneyStore } from '@/src/core/auth';
import { useTzStores } from '../hooks/useCatalogQueries';
import { useCatalogUiStore } from '../state/catalogUiStore';

/**
 * Browse-only TZ store selection helpers.
 * - Validates persisted selection against server stores; clears if invalid.
 * - When empty, adopts first server store for Browse scope only.
 * Never used as product ownership for Home/Search/deep-link.
 */
export function useEnsureDefaultTzStore() {
  const journey = useJourneyStore((s) => s.journey);
  const selectedTzStoreSlug = useCatalogUiStore((s) => s.selectedTzStoreSlug);
  const setSelectedTzStoreSlug = useCatalogUiStore((s) => s.setSelectedTzStoreSlug);
  const storesQuery = useTzStores();

  useEffect(() => {
    if (journey !== 'TZ_LOCAL') return;
    const stores = storesQuery.data;
    if (!stores) return;

    if (selectedTzStoreSlug) {
      const stillValid = stores.some((store) => store.slug === selectedTzStoreSlug);
      if (!stillValid) {
        setSelectedTzStoreSlug(null);
      }
      return;
    }

    const first = stores[0]?.slug;
    if (first) {
      setSelectedTzStoreSlug(first);
    }
  }, [
    journey,
    selectedTzStoreSlug,
    setSelectedTzStoreSlug,
    storesQuery.data,
  ]);

  return {
    selectedTzStoreSlug,
    storesLoading: journey === 'TZ_LOCAL' && storesQuery.isLoading && !storesQuery.data,
    storesError: storesQuery.isError,
  };
}
