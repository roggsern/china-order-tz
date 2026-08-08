"use client";

import {
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type KeyboardEvent,
  type ReactNode,
} from "react";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { ADMIN_SEARCH_DEBOUNCE_MS } from "@/lib/admin/admin-search-utils";

export type AdminAsyncOption = {
  id: string;
  label: string;
  description?: string;
  indent?: number;
};

type LoadResult = {
  items: AdminAsyncOption[];
  hasMore: boolean;
};

type AdminAsyncSearchSelectProps = {
  id: string;
  value: string;
  selectedLabel?: string;
  placeholder?: string;
  disabled?: boolean;
  emptyMessage?: string;
  footer?: ReactNode;
  loadOptions: (query: string, page: number) => Promise<LoadResult>;
  onChange: (id: string, option: AdminAsyncOption | null) => void;
  /** Extra deps that should re-run the search (e.g. category filter). */
  reloadKey?: string;
  /** Override search debounce (use 0 for local/instant lists). */
  debounceMs?: number;
};

export function AdminAsyncSearchSelect({
  id,
  value,
  selectedLabel,
  placeholder = "Type to search…",
  disabled = false,
  emptyMessage = "No matches found.",
  footer,
  loadOptions,
  onChange,
  reloadKey = "",
  debounceMs = ADMIN_SEARCH_DEBOUNCE_MS,
}: AdminAsyncSearchSelectProps) {
  const listboxId = useId();
  const containerRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const debouncedQuery = useDebouncedValue(query, debounceMs);
  const [options, setOptions] = useState<AdminAsyncOption[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(false);
  const [highlightIndex, setHighlightIndex] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const displayValue = open ? query : selectedLabel || query;

  const runLoad = useCallback(
    async (search: string, nextPage: number, append: boolean) => {
      setLoading(true);
      setError(null);
      try {
        const result = await loadOptions(search, nextPage);
        setOptions((prev) => (append ? [...prev, ...result.items] : result.items));
        setHasMore(result.hasMore);
        setPage(nextPage);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Unable to load options.");
        if (!append) {
          setOptions([]);
          setHasMore(false);
        }
      } finally {
        setLoading(false);
      }
    },
    [loadOptions],
  );

  useEffect(() => {
    if (!open) {
      return;
    }
    void runLoad(debouncedQuery, 1, false);
  }, [open, debouncedQuery, reloadKey, runLoad]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
        setQuery("");
      }
    };
    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open]);

  useEffect(() => {
    setHighlightIndex(0);
  }, [options, open]);

  const selectOption = (option: AdminAsyncOption) => {
    onChange(option.id, option);
    setQuery("");
    setOpen(false);
  };

  const clearSelection = () => {
    onChange("", null);
    setQuery("");
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!open && (event.key === "ArrowDown" || event.key === "Enter")) {
      event.preventDefault();
      setOpen(true);
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setHighlightIndex((index) => Math.min(index + 1, Math.max(options.length - 1, 0)));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setHighlightIndex((index) => Math.max(index - 1, 0));
    } else if (event.key === "Enter" && options[highlightIndex]) {
      event.preventDefault();
      selectOption(options[highlightIndex]);
    } else if (event.key === "Escape") {
      setOpen(false);
      setQuery("");
    }
  };

  return (
    <div ref={containerRef} className="relative">
      <div className="flex gap-2">
        <input
          id={id}
          type="text"
          role="combobox"
          autoComplete="off"
          aria-expanded={open}
          aria-controls={listboxId}
          aria-autocomplete="list"
          disabled={disabled}
          className="admin-input mt-1.5 w-full"
          placeholder={placeholder}
          value={displayValue}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={handleKeyDown}
        />
        {value ? (
          <button
            type="button"
            className="admin-btn-secondary mt-1.5 shrink-0 px-2 text-xs"
            onClick={clearSelection}
            disabled={disabled}
          >
            Clear
          </button>
        ) : null}
      </div>

      {value && selectedLabel && !open ? (
        <p className="mt-1 text-xs font-medium text-zinc-700">
          Selected: <span className="text-zinc-900">{selectedLabel}</span>
        </p>
      ) : null}

      {open ? (
        <div className="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg">
          <ul id={listboxId} role="listbox" className="max-h-60 overflow-y-auto py-1">
            {loading && options.length === 0 ? (
              <li className="px-3 py-2 text-sm text-zinc-500">Searching…</li>
            ) : null}
            {!loading && options.length === 0 ? (
              <li className="px-3 py-2 text-sm text-zinc-500">{error ?? emptyMessage}</li>
            ) : null}
            {options.map((option, index) => (
              <li key={option.id} role="option" aria-selected={value === option.id}>
                <button
                  type="button"
                  className={`flex w-full flex-col px-3 py-2 text-left text-sm transition ${
                    index === highlightIndex ? "bg-[#c9a227]/15" : "hover:bg-zinc-50"
                  } ${value === option.id ? "font-semibold text-zinc-900" : "text-zinc-700"}`}
                  style={{ paddingLeft: `${12 + (option.indent ?? 0) * 14}px` }}
                  onMouseEnter={() => setHighlightIndex(index)}
                  onClick={() => selectOption(option)}
                >
                  <span>{option.label}</span>
                  {option.description ? (
                    <span className="text-xs font-normal text-zinc-500">{option.description}</span>
                  ) : null}
                </button>
              </li>
            ))}
          </ul>
          {hasMore ? (
            <button
              type="button"
              className="w-full border-t border-zinc-100 px-3 py-2 text-left text-xs font-semibold text-[#8b6914] hover:bg-zinc-50"
              onClick={() => void runLoad(debouncedQuery, page + 1, true)}
              disabled={loading}
            >
              {loading ? "Loading…" : "Load more"}
            </button>
          ) : null}
          {footer ? <div className="border-t border-zinc-100 px-3 py-2">{footer}</div> : null}
        </div>
      ) : null}
    </div>
  );
}
