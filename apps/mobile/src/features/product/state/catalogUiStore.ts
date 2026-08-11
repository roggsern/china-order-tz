import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import * as SecureStore from 'expo-secure-store';

type CatalogUiState = {
  /** Explicitly user-selected TZ store slug for Browse (persisted). */
  selectedTzStoreSlug: string | null;
  setSelectedTzStoreSlug: (slug: string | null) => void;
  /** China Browse category slug from Home / search deep-link (persisted). */
  selectedChinaCategorySlug: string | null;
  setSelectedChinaCategorySlug: (slug: string | null) => void;
  /** TZ Browse category slug from search deep-link (persisted per store context). */
  selectedTzCategorySlug: string | null;
  setSelectedTzCategorySlug: (slug: string | null) => void;
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
      selectedChinaCategorySlug: null,
      setSelectedChinaCategorySlug: (slug) =>
        set({ selectedChinaCategorySlug: slug }),
      selectedTzCategorySlug: null,
      setSelectedTzCategorySlug: (slug) =>
        set({ selectedTzCategorySlug: slug }),
    }),
    {
      name: 'cotz.catalog_ui.v3',
      storage: securePersistStorage,
      partialize: (state) => ({
        selectedTzStoreSlug: state.selectedTzStoreSlug,
        selectedChinaCategorySlug: state.selectedChinaCategorySlug,
        selectedTzCategorySlug: state.selectedTzCategorySlug,
      }),
    },
  ),
);
