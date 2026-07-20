"use client";

import { useEffect, useState } from "react";
import { getTzStores, type TzStorefrontStore } from "@/lib/api/tz-stores";

type State = {
  stores: TzStorefrontStore[];
  isLoading: boolean;
  error: string | null;
};

export function useTzStores(): State {
  const [state, setState] = useState<State>({
    stores: [],
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    let active = true;
    void getTzStores()
      .then((stores) => {
        if (!active) return;
        setState({ stores, isLoading: false, error: null });
      })
      .catch((error: unknown) => {
        if (!active) return;
        setState({
          stores: [],
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load stores.",
        });
      });
    return () => {
      active = false;
    };
  }, []);

  return state;
}
