import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import * as SecureStore from 'expo-secure-store';
import {
  type CommerceJourney,
  isCommerceJourney,
} from '@/src/shared/types/commerce';

type JourneyState = {
  journey: CommerceJourney;
  setJourney: (journey: CommerceJourney) => void;
};

const securePersistStorage = createJSONStorage(() => ({
  getItem: (name) => SecureStore.getItemAsync(name),
  setItem: (name, value) => SecureStore.setItemAsync(name, value),
  removeItem: (name) => SecureStore.deleteItemAsync(name),
}));

/**
 * Active commerce journey for CMS/catalog navigation context.
 * Values must match backend: CHINA_IMPORT | TZ_LOCAL.
 * Persists explicit user selection across restarts.
 */
export const useJourneyStore = create<JourneyState>()(
  persist(
    (set) => ({
      journey: 'CHINA_IMPORT',
      setJourney: (journey) => {
        if (!isCommerceJourney(journey)) {
          return;
        }
        set({ journey });
      },
    }),
    {
      name: 'cotz.journey.v1',
      storage: securePersistStorage,
      partialize: (state) => ({ journey: state.journey }),
    },
  ),
);
