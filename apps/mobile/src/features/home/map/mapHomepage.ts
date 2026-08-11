import { ApiError } from '@/src/core/errors';
import type { CommerceJourney } from '@/src/shared/types/commerce';
import { homepageResponseSchema } from '../api/schemas';
import type {
  HomepageFeaturedContent,
  HomepageHeroSlide,
  HomepageLayout,
  HomepageMeta,
  HomepageProductCard,
  HomepageSection,
  HomepageViewModel,
  HomepageCommerceContext,
  RenderableHomepageSection,
} from '../models/types';

const PRODUCT_SECTION_TYPES = new Set([
  'FEATURED_PRODUCTS',
  'NEW_ARRIVALS',
  'BEST_SELLERS',
]);

export function resolveHomepageCommerceContext(
  journey: CommerceJourney,
): HomepageCommerceContext {
  return journey;
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function mediaUrl(media: unknown): string | null {
  const record = asRecord(media);
  return stringField(record, 'url');
}

/** Map a CMS featured PRODUCT item `data` blob into a mobile card (no hardcoded catalog). */
export function mapProductCard(data: unknown): HomepageProductCard | null {
  const record = asRecord(data);
  const id = stringField(record, 'id');
  const name = stringField(record, 'name');
  if (!id || !name) {
    return null;
  }

  const primaryImage = asRecord(record.primary_image);
  const imageUrl =
    mediaUrl(record.primary_image) ??
    stringField(record, 'image_url') ??
    stringField(primaryImage, 'url');

  const price =
    (typeof record.price === 'string' || typeof record.price === 'number'
      ? record.price
      : null) ?? null;

  const compareAtPrice =
    typeof record.compare_at_price === 'string' || typeof record.compare_at_price === 'number'
      ? record.compare_at_price
      : null;

  const store = asRecord(record.store);
  const storeSlug =
    stringField(record, 'store_slug') ??
    stringField(record, 'storeSlug') ??
    stringField(store, 'slug');

  return {
    id,
    slug: stringField(record, 'slug') ?? id,
    name,
    price,
    compareAtPrice,
    imageUrl,
    commerceChannelCode: stringField(record, 'commerce_channel_code'),
    storeSlug,
  };
}

export function collectFeaturedItems(
  featuredContents: HomepageFeaturedContent[] | undefined,
): HomepageFeaturedContent['items'] {
  if (!featuredContents?.length) return [];
  return [...featuredContents]
    .sort((a, b) => a.position - b.position)
    .flatMap((block) => block.items ?? []);
}

export function productsFromFeatured(
  featuredContents: HomepageFeaturedContent[] | undefined,
): HomepageProductCard[] {
  const products: HomepageProductCard[] = [];
  for (const item of collectFeaturedItems(featuredContents) ?? []) {
    if ((item.item_type || '').toUpperCase() !== 'PRODUCT') continue;
    const product = mapProductCard(item.data);
    if (product) products.push(product);
  }
  return products;
}

function sortedVisibleSections(sections: HomepageSection[]): HomepageSection[] {
  return [...sections]
    .filter((section) => section.is_visible !== false)
    .sort((a, b) => a.position - b.position);
}

function sortedSlides(slides: HomepageHeroSlide[] | undefined): HomepageHeroSlide[] {
  if (!slides?.length) return [];
  return [...slides].sort((a, b) => a.position - b.position);
}

/**
 * Build reusable render list from CMS layout + campaign meta.
 * Unknown section types are ignored safely.
 */
export function buildRenderableSections(
  layout: HomepageLayout | null,
  meta: HomepageMeta,
): RenderableHomepageSection[] {
  const heroes: RenderableHomepageSection[] = [];
  const products: RenderableHomepageSection[] = [];

  if (layout) {
    for (const section of sortedVisibleSections(layout.sections ?? [])) {
      const type = (section.section_type || '').toUpperCase();

      if (type === 'HERO') {
        heroes.push({
          kind: 'HERO',
          key: section.id,
          title: section.title,
          subtitle: section.subtitle,
          slides: sortedSlides(section.hero_slides),
        });
        continue;
      }

      if (PRODUCT_SECTION_TYPES.has(type)) {
        products.push({
          kind: type as 'FEATURED_PRODUCTS' | 'NEW_ARRIVALS' | 'BEST_SELLERS',
          key: section.id,
          title: section.title,
          subtitle: section.subtitle,
          products: productsFromFeatured(section.featured_contents),
        });
      }
    }
  }

  const out: RenderableHomepageSection[] = [...heroes];

  if (meta.campaign) {
    out.push({
      kind: 'CAMPAIGN',
      key: `campaign:${meta.campaign.id}`,
      campaign: meta.campaign,
    });
  }

  out.push(...products);
  return out;
}

export function mapHomepageResponse(raw: unknown): HomepageViewModel {
  const parsed = homepageResponseSchema.safeParse(raw);
  if (!parsed.success) {
    const payload =
      raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : null;
    if (payload && payload.success === false) {
      throw new ApiError({
        message:
          (typeof payload.message === 'string' && payload.message) ||
          'Homepage request failed',
        status: 500,
        code: typeof payload.code === 'string' ? payload.code : 'server_error',
        errors:
          payload.errors && typeof payload.errors === 'object'
            ? (payload.errors as Record<string, string[]>)
            : undefined,
        raw: payload as never,
      });
    }

    throw new ApiError({
      message: 'Unexpected homepage response',
      status: 500,
      code: 'server_error',
      raw: payload as never,
    });
  }

  const layout = (parsed.data.data ?? null) as HomepageLayout | null;
  const meta = parsed.data.meta as HomepageMeta;
  return {
    layout,
    meta,
    sections: buildRenderableSections(layout, meta),
  };
}

export function homepageQueryKey(commerceContext: HomepageCommerceContext) {
  return ['storefront', 'homepage', commerceContext] as const;
}
