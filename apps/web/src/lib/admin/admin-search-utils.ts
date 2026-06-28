export const ADMIN_SEARCH_DEBOUNCE_MS = 300;

export function normalizeAdminSearchQuery(query: string): string {
  return query.trim().toLowerCase();
}

export function compactAdminSearchText(value: string): string {
  return value.toLowerCase().replace(/\s+/g, "");
}

/** Multi-term match; supports spaced queries like "hand bag" → "handbag". */
export function matchesAdminSearchTerms(haystack: string, query: string): boolean {
  const trimmed = normalizeAdminSearchQuery(query);
  if (!trimmed) {
    return true;
  }

  const terms = trimmed.split(/\s+/).filter(Boolean);
  const searchable = haystack.toLowerCase();
  const compactSearchable = compactAdminSearchText(haystack);

  return terms.every((term) => {
    const compactTerm = compactAdminSearchText(term);
    return searchable.includes(term) || compactSearchable.includes(compactTerm);
  });
}

export function buildAdminSearchHaystack(parts: Array<string | undefined | null>): string {
  return parts.filter(Boolean).join(" ");
}
