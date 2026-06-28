import { isFuzzyMatch } from "@/lib/search/fuzzy";

/** Normalize user search input for consistent matching. */
export function normalizeSearchInput(input: string): string {
  return input.trim().toLowerCase().replace(/\s+/g, " ");
}

/** Expand a term with simple plural/singular variants (bag ↔ bags, top ↔ tops). */
export function expandTermVariants(term: string): string[] {
  const normalized = normalizeSearchInput(term);
  if (!normalized) {
    return [];
  }

  const variants = new Set<string>([normalized]);

  if (normalized.length > 3 && normalized.endsWith("s") && !normalized.endsWith("ss")) {
    variants.add(normalized.slice(0, -1));
  }

  if (normalized.length > 2 && !normalized.endsWith("s")) {
    variants.add(`${normalized}s`);
  }

  if (normalized.length > 4 && normalized.endsWith("ies")) {
    variants.add(`${normalized.slice(0, -3)}y`);
  }

  if (normalized.length > 2 && normalized.endsWith("y")) {
    variants.add(`${normalized.slice(0, -1)}ies`);
  }

  return [...variants];
}

export function tokenizeSearchQuery(query: string): string[] {
  return normalizeSearchInput(query).split(" ").filter(Boolean);
}

export function normalizeSearchableText(text: string): string {
  return normalizeSearchInput(text).replace(/-/g, " ");
}

export function textMatchesAnyVariant(text: string, variants: string[]): boolean {
  if (variants.length === 0) {
    return false;
  }

  const normalized = normalizeSearchableText(text);
  return variants.some((variant) => normalized.includes(variant));
}

function textWords(text: string): string[] {
  return normalizeSearchableText(text).split(" ").filter(Boolean);
}

/** Case-insensitive partial + fuzzy match for a single search term inside a text field. */
export function termMatchesInText(text: string, term: string): boolean {
  const variants = expandTermVariants(term);
  if (variants.length === 0) {
    return false;
  }

  if (textMatchesAnyVariant(text, variants)) {
    return true;
  }

  const words = textWords(text);
  for (const word of words) {
    for (const variant of variants) {
      if (isFuzzyMatch(word, variant)) {
        return true;
      }
    }
  }

  return false;
}

/** Every query token must match at least one of the provided text fields. */
export function allTokensMatchFields(tokens: string[], fields: string[]): boolean {
  if (tokens.length === 0) {
    return false;
  }

  return tokens.every((token) => fields.some((field) => termMatchesInText(field, token)));
}
