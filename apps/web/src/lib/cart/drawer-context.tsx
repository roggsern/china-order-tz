"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

type CartDrawerContextValue = {
  isOpen: boolean;
  drawerActive: boolean;
  open: () => void;
  close: () => void;
  toggle: () => void;
};

const CartDrawerContext = createContext<CartDrawerContextValue | null>(null);

export function CartDrawerProvider({ children }: { children: ReactNode }) {
  const [isOpen, setIsOpen] = useState(false);
  const [drawerActive, setDrawerActive] = useState(false);

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = "hidden";
      const frame = requestAnimationFrame(() => setDrawerActive(true));
      return () => cancelAnimationFrame(frame);
    }

    setDrawerActive(false);
    document.body.style.overflow = "";
  }, [isOpen]);

  const close = useCallback(() => {
    setDrawerActive(false);
    window.setTimeout(() => setIsOpen(false), 300);
  }, []);

  const open = useCallback(() => {
    setIsOpen(true);
  }, []);

  const toggle = useCallback(() => {
    if (isOpen) {
      close();
    } else {
      open();
    }
  }, [close, isOpen, open]);

  const value = useMemo(
    () => ({ isOpen, drawerActive, open, close, toggle }),
    [isOpen, drawerActive, open, close, toggle],
  );

  return <CartDrawerContext.Provider value={value}>{children}</CartDrawerContext.Provider>;
}

export function useCartDrawer(): CartDrawerContextValue {
  const context = useContext(CartDrawerContext);
  if (!context) {
    throw new Error("useCartDrawer must be used within CartDrawerProvider");
  }
  return context;
}
