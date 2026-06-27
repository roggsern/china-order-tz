"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { SearchIcon } from "@/components/home/icons";
import { useSearchSuggestions } from "@/hooks/use-search-suggestions";
import { addRecentSearch, clearRecentSearches } from "@/lib/search/recent-searches";
import { SearchResultsPanel } from "./SearchResultsPanel";

interface SearchExperienceProps {
  className?: string;
  placeholder?: string;
  size?: "default" | "large";
  inputId?: string;
}

export function SearchExperience({
  className = "",
  placeholder = "Search products, brands or categories...",
  size = "default",
  inputId,
}: SearchExperienceProps) {
  const router = useRouter();
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const { results, recentSearches, isLoading, isSearching } = useSearchSuggestions(query, open);

  const isLarge = size === "large";

  useEffect(() => {
    if (!open) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        inputRef.current?.blur();
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    window.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [open]);

  const handleSelect = useCallback(
    (href: string, label?: string) => {
      if (label) {
        addRecentSearch(label);
      }
      setOpen(false);
      setQuery("");
      router.push(href);
    },
    [router],
  );

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    const trimmed = query.trim();
    if (trimmed) {
      addRecentSearch(trimmed);
      setOpen(false);
      router.push(`/products?q=${encodeURIComponent(trimmed)}`);
    } else {
      router.push("/products");
    }
  };

  return (
    <div ref={containerRef} className={`relative ${className}`}>
      <form onSubmit={handleSubmit}>
        <label className="group relative block">
          <span className="sr-only">Search products</span>
          <SearchIcon
            className={`pointer-events-none absolute top-1/2 -translate-y-1/2 text-zinc-400 transition-colors group-focus-within:text-[#c9a227] ${
              isLarge ? "left-4 h-5 w-5" : "left-4 h-[18px] w-[18px]"
            }`}
          />
          <input
            id={inputId}
            ref={inputRef}
            type="search"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            placeholder={placeholder}
            autoComplete="off"
            role="combobox"
            aria-expanded={open}
            aria-controls="desktop-search-results"
            aria-autocomplete="list"
            className={`group w-full border border-zinc-200/90 bg-white text-zinc-900 outline-none transition-all placeholder:text-zinc-400 focus:border-[#c9a227] focus:ring-0 ${
              isLarge
                ? "h-[52px] rounded-xl pl-12 pr-5 text-[15px] shadow-none hover:border-zinc-300 focus:shadow-[0_0_0_3px_rgba(201,162,39,0.12)]"
                : "rounded-full py-2.5 pl-11 pr-4 text-sm shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:border-zinc-300 hover:shadow-[0_4px_16px_rgba(0,0,0,0.08)] focus:shadow-[0_4px_20px_rgba(201,162,39,0.18),0_0_0_3px_rgba(201,162,39,0.12)]"
            }`}
          />
        </label>
      </form>

      <AnimatePresence>
        {open ? (
          <motion.div
            key="search-dropdown"
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.2, ease: "easeOut" }}
            id="desktop-search-results"
            role="listbox"
            className="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-50 overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-[0_16px_48px_rgba(0,0,0,0.12)]"
          >
            <SearchResultsPanel
              query={query}
              results={results}
              recentSearches={recentSearches}
              isLoading={isLoading}
              isSearching={isSearching}
              onSelect={handleSelect}
              onClearRecent={() => clearRecentSearches()}
            />
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}
