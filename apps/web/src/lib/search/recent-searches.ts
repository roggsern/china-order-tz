const RECENT_SEARCHES_KEY = "china-order-tz-recent-searches";
const MAX_RECENT = 8;

export function getRecentSearches(): string[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(RECENT_SEARCHES_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as string[];
    return Array.isArray(parsed) ? parsed.filter(Boolean).slice(0, MAX_RECENT) : [];
  } catch {
    return [];
  }
}

export function addRecentSearch(term: string): void {
  if (typeof window === "undefined") {
    return;
  }

  const trimmed = term.trim();
  if (!trimmed) {
    return;
  }

  const next = [trimmed, ...getRecentSearches().filter((entry) => entry.toLowerCase() !== trimmed.toLowerCase())].slice(
    0,
    MAX_RECENT,
  );

  window.localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
  window.dispatchEvent(new Event("recent-searches-updated"));
}

export function clearRecentSearches(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(RECENT_SEARCHES_KEY);
  window.dispatchEvent(new Event("recent-searches-updated"));
}
