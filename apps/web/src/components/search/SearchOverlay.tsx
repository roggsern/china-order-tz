"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useRouter } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { CloseIcon, SearchIcon } from "@/components/home/icons";
import { useSearchSuggestions } from "@/hooks/use-search-suggestions";
import { addRecentSearch, clearRecentSearches } from "@/lib/search/recent-searches";
import { SearchResultsPanel } from "./SearchResultsPanel";

interface SearchOverlayProps {
  open: boolean;
  onClose: () => void;
}

export function SearchOverlay({ open, onClose }: SearchOverlayProps) {
  const router = useRouter();
  const [query, setQuery] = useState("");
  const inputRef = useRef<HTMLInputElement>(null);
  const { results, recentSearches, isLoading, isSearching } = useSearchSuggestions(query, open);

  useEffect(() => {
    if (!open) {
      setQuery("");
      return;
    }

    document.body.style.overflow = "hidden";
    const timer = window.setTimeout(() => inputRef.current?.focus(), 100);

    return () => {
      window.clearTimeout(timer);
      document.body.style.overflow = "";
    };
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  const handleSelect = useCallback(
    (href: string, label?: string) => {
      if (label) {
        addRecentSearch(label);
      }
      onClose();
      router.push(href);
    },
    [onClose, router],
  );

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    const trimmed = query.trim();
    if (trimmed) {
      addRecentSearch(trimmed);
      onClose();
      router.push(`/products?q=${encodeURIComponent(trimmed)}`);
    }
  };

  if (typeof document === "undefined") {
    return null;
  }

  return createPortal(
    <AnimatePresence>
      {open ? (
        <motion.div
          key="search-overlay"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2 }}
          className="fixed inset-0 z-[70] flex flex-col bg-white md:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Search"
        >
          <div className="flex shrink-0 items-center gap-2 border-b border-zinc-100 px-3 py-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
            <form onSubmit={handleSubmit} className="relative min-w-0 flex-1">
              <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" />
              <input
                ref={inputRef}
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search products, brands or categories..."
                autoComplete="off"
                enterKeyHint="search"
                className="w-full rounded-xl border border-zinc-200 bg-zinc-50 py-3 pl-10 pr-4 text-base text-zinc-900 outline-none transition focus:border-[#c9a227] focus:bg-white focus:shadow-[0_0_0_3px_rgba(201,162,39,0.12)]"
              />
            </form>
            <button
              type="button"
              onClick={onClose}
              className="inline-flex shrink-0 items-center justify-center rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100"
              aria-label="Close search"
            >
              <CloseIcon className="h-5 w-5" />
            </button>
          </div>

          <div className="min-h-0 flex-1 overflow-hidden">
            <SearchResultsPanel
              query={query}
              results={results}
              recentSearches={recentSearches}
              isLoading={isLoading}
              isSearching={isSearching}
              onSelect={handleSelect}
              onClearRecent={() => {
                clearRecentSearches();
              }}
              className="h-full"
            />
          </div>
        </motion.div>
      ) : null}
    </AnimatePresence>,
    document.body,
  );
}
