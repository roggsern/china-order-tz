import {
  searchEntitySuggestionSchema,
  searchHitSchema,
  searchProductsMetaSchema,
  searchSuggestDataSchema,
} from '../api/schemas';
import type {
  SearchHit,
  SearchResponse,
  SearchSuggestion,
  SearchSuggestionsResult,
} from '../models/types';
import { preferStorefrontImageSrcFromUnknown } from '@/src/shared/media/preferStorefrontImageSrc';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringId(value: unknown): string {
  if (typeof value === 'string' || typeof value === 'number') {
    return String(value);
  }
  return '';
}

function mediaUrl(media: unknown): string | null {
  return preferStorefrontImageSrcFromUnknown(media);
}

function asBoolean(value: unknown): boolean | null {
  if (typeof value === 'boolean') return value;
  return null;
}

export function mapSearchHit(raw: unknown): SearchHit | null {
  const parsed = searchHitSchema.safeParse(raw);
  if (!parsed.success) return null;

  const data = parsed.data;
  const id = stringId(data.id);
  if (!id || !data.name) return null;

  const store = data.store ? asRecord(data.store) : {};
  const brand = data.brand ? asRecord(data.brand) : {};

  return {
    id,
    slug: typeof data.slug === 'string' && data.slug.trim() !== '' ? data.slug : id,
    name: data.name,
    price: data.price ?? null,
    compareAtPrice: data.compare_at_price ?? null,
    imageUrl: mediaUrl(data.primary_image),
    marketplace:
      typeof data.marketplace === 'string' && data.marketplace.trim() !== ''
        ? data.marketplace.trim()
        : null,
    commerceChannelCode:
      typeof data.commerce_channel_code === 'string' ? data.commerce_channel_code : null,
    storeSlug: typeof store.slug === 'string' ? store.slug : null,
    storeName: typeof store.name === 'string' ? store.name : null,
    brandName: typeof brand.name === 'string' ? brand.name : null,
    relevanceScore: typeof data.relevance_score === 'number' ? data.relevance_score : null,
    availabilityStatus:
      typeof data.availability_status === 'string' ? data.availability_status : null,
    isPurchasable: asBoolean(data.is_purchasable),
    inStock: asBoolean(data.in_stock),
    matchedOn: Array.isArray(data.matched_on)
      ? data.matched_on.filter((item): item is string => typeof item === 'string')
      : undefined,
  };
}

function mapEntitySuggestion(
  raw: unknown,
  kind: SearchSuggestion['kind'],
): SearchSuggestion | null {
  const parsed = searchEntitySuggestionSchema.safeParse(raw);
  if (!parsed.success) return null;
  const data = parsed.data;
  const id = stringId(data.id);
  if (!id || !data.name) return null;
  return {
    kind,
    id: `${kind}:${id}`,
    label: data.name,
    query: data.name,
    slug: typeof data.slug === 'string' ? data.slug : null,
  };
}

function mapProductSuggestion(raw: unknown): SearchSuggestion | null {
  const hit = mapSearchHit(raw);
  if (!hit) return null;
  return {
    kind: 'product',
    id: `product:${hit.id}`,
    label: hit.brandName ? `${hit.name} · ${hit.brandName}` : hit.name,
    query: hit.name,
    slug: hit.slug,
  };
}

/** Flatten suggest buckets into a single suggestion list for the UI. */
export function mapSearchSuggestions(envelope: {
  data?: unknown;
}): SearchSuggestionsResult {
  const parsed = searchSuggestDataSchema.safeParse(envelope.data ?? {});
  const data = parsed.success
    ? parsed.data
    : { q: '', scope: 'all', products: [], brands: [], stores: [], categories: [] };

  const suggestions: SearchSuggestion[] = [];

  for (const row of data.products ?? []) {
    const suggestion = mapProductSuggestion(row);
    if (suggestion) suggestions.push(suggestion);
  }
  for (const row of data.brands ?? []) {
    const suggestion = mapEntitySuggestion(row, 'brand');
    if (suggestion) suggestions.push(suggestion);
  }
  for (const row of data.stores ?? []) {
    const suggestion = mapEntitySuggestion(row, 'store');
    if (suggestion) suggestions.push(suggestion);
  }
  for (const row of data.categories ?? []) {
    const suggestion = mapEntitySuggestion(row, 'category');
    if (suggestion) suggestions.push(suggestion);
  }

  return {
    q: data.q ?? '',
    scope: data.scope ?? 'all',
    suggestions,
  };
}

export function mapSearchProductsResponse(envelope: {
  data?: unknown;
  meta?: unknown;
}): SearchResponse {
  const metaParsed = searchProductsMetaSchema.safeParse(envelope.meta ?? {});
  const meta = metaParsed.success
    ? metaParsed.data
    : {
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 0,
        q: '',
        scope: 'all',
      };

  const rows = Array.isArray(envelope.data) ? envelope.data : [];
  const hits = rows
    .map(mapSearchHit)
    .filter((hit): hit is SearchHit => hit !== null);

  return {
    hits,
    page: meta.current_page,
    lastPage: Math.max(1, meta.last_page),
    perPage: meta.per_page,
    // Prefer mapped length when the server total is larger but rows were dropped —
    // UI empty-state must not claim "no results" while rows existed but failed parse.
    // After schema hardening, mapped count should match; keep server total as authority.
    total: meta.total,
    q: meta.q,
    scope: meta.scope,
  };
}

/**
 * True when the API reported hits but the client could not map any.
 * Used to avoid false "No results" empty states.
 */
export function isSearchMappingFailure(response: SearchResponse): boolean {
  return response.total > 0 && response.hits.length === 0;
}
