import { create } from 'zustand';
import type { NmbBrowserSessionResult } from '../utils/nmbBrowser';

export type NmbWebsiteCheckoutRequest = {
  sessionId: string;
  gatewayBaseUrl: string;
};

type NmbWebsiteCheckoutState = {
  request: NmbWebsiteCheckoutRequest | null;
  resolve: ((result: NmbBrowserSessionResult) => void) | null;
  open: (
    request: NmbWebsiteCheckoutRequest,
  ) => Promise<NmbBrowserSessionResult>;
  complete: (result: NmbBrowserSessionResult) => void;
};

export const useNmbWebsiteCheckoutStore = create<NmbWebsiteCheckoutState>((set, get) => ({
  request: null,
  resolve: null,
  open: (request) =>
    new Promise<NmbBrowserSessionResult>((resolve) => {
      set({ request, resolve });
    }),
  complete: (result) => {
    const { resolve } = get();
    set({ request: null, resolve: null });
    resolve?.(result);
  },
}));
