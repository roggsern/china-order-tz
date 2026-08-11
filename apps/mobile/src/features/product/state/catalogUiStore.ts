import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import * as SecureStore from 'expo-secure-store';

type CatalogUiState = {
  /** Explicitly user-selected TZ store slug for Browse (persisted). */
  selectedTzStoreSlug: string | null;
  setSelectedTzStoreSlug: (slug: string | null) => void;
};

const securePersistStorage = createJSONStorage(() => ({
  getItem: (name) => SecureStore.getItemAsync(name),
  setItem: (name, value) => SecureStore.setItemAsync(name, value),
  removeItem: (name) => SecureStore.deleteItemAsync(name),
}));

/**
 * Browse UI selection only — never a substitute for product ownership store.
 */
export const useCatalogUiStore = create<CatalogUiState>()(
  persist(
    (set) => ({
      selectedTzStoreSlug: null,
      setSelectedTzStoreSlug: (slug) => set({ selectedTzStoreSlug: slug }),
    }),
    {
      name: 'cotz.catalog_ui.v1',
      storage: securePersistStorage,
      partialize: (state) => ({
        selectedTzStoreSlug: state.selectedTzStoreSlug,
      }),
    },
  ),
);
