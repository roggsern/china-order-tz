"use client";

import { useEffect, useState } from "react";
import {
  getChinaStorefrontMenu,
  type ChinaStorefrontMenu,
} from "@/lib/api/china-storefront";

type State = {
  menu: ChinaStorefrontMenu | null;
  isLoading: boolean;
  error: string | null;
};

export function useChinaStorefrontMenu(category?: string): State {
  const [state, setState] = useState<State>({
    menu: null,
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    let active = true;
    const isInitial = !category;

    if (isInitial) {
      setState((prev) => ({ ...prev, isLoading: true, error: null }));
    }

    void getChinaStorefrontMenu(category)
      .then((menu) => {
        if (!active) return;
        setState({ menu, isLoading: false, error: null });
      })
      .catch((error: unknown) => {
        if (!active) return;
        setState((prev) => ({
          menu: prev.menu,
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load ORDER FROM CHINA.",
        }));
      });

    return () => {
      active = false;
    };
  }, [category]);

  return state;
}
