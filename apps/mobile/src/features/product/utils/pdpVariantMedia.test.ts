import {
  PDP_GALLERY_CACHE_POLICY,
  PDP_VARIANT_MEDIA_PREFETCH_LIMIT,
  applyHeldGalleryPrefetchResult,
  collectPdpVariantPrefetchUrls,
  createHeldGalleryState,
  firstImageSlideUrls,
  galleryImageIdentity,
  pdpGalleryImageProps,
  planHeldGalleryUpdate,
} from './pdpVariantMedia';
import { resolvePdpGalleryMediaFromPdpState } from './resolvePdpGalleryMedia';
import { LIST_IMAGE_CACHE_POLICY } from '@/src/shared/media/listImageProps';
import { mapProductDetail } from '../map/mapProduct';
import type {
  CatalogProductVariant,
  ProductConfiguration,
  ProductConfigurationAttribute,
  ProductConfigurationRow,
} from '../models/types';
import type { ProductGalleryMediaSlide } from './resolvePdpGalleryMedia';

const productImages = [{ id: 'p', url: 'https://cdn.example/product.jpg' }];

const colorAttr: ProductConfigurationAttribute = {
  id: 'attr-color',
  name: 'Color',
  slug: 'color',
  type: 'color',
  isVisual: true,
  isRequired: true,
  participatesInConfiguration: true,
  values: [
    { id: 'val-red', value: 'Red' },
    { id: 'val-blue', value: 'Blue' },
    { id: 'val-green', value: 'Green' },
  ],
};

const sizeAttr: ProductConfigurationAttribute = {
  id: 'attr-size',
  name: 'Size',
  slug: 'size',
  isRequired: true,
  participatesInConfiguration: true,
  values: [
    { id: 'val-s', value: 'S' },
    { id: 'val-m', value: 'M' },
  ],
};

const configurations: ProductConfigurationRow[] = [
  {
    id: 'cfg-red-s',
    attributeValueIds: ['val-red', 'val-s'],
    inStock: true,
    stock: 3,
  },
  {
    id: 'cfg-blue-s',
    attributeValueIds: ['val-blue', 'val-s'],
    inStock: true,
    stock: 2,
  },
  {
    id: 'cfg-green-s',
    attributeValueIds: ['val-green', 'val-s'],
    inStock: true,
    stock: 1,
  },
];

const variants: CatalogProductVariant[] = [
  {
    id: 'cfg-red-s',
    price: 1000,
    images: [
      { id: 'r1', url: 'https://cdn.example/red.jpg' },
      { id: 'r2', url: 'https://cdn.example/red-2.jpg' },
    ],
  },
  {
    id: 'cfg-blue-s',
    price: 1000,
    images: [{ id: 'b1', url: 'https://cdn.example/blue.jpg' }],
  },
  {
    id: 'cfg-green-s',
    price: 1000,
    images: [{ id: 'g1', url: 'https://cdn.example/green.jpg' }],
  },
];

const configuration: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: true,
  isComplete: false,
  isInStock: true,
  matchedConfigurationId: null,
  matchedUnitPrice: null,
  attributes: [colorAttr, sizeAttr],
  configurations,
  allowedValueIds: {},
  capabilities: {},
};

function imageSlide(
  url: string,
  id?: string,
): ProductGalleryMediaSlide {
  return {
    kind: 'image',
    key: `image-${id ?? url}`,
    image: { id, url },
  };
}

describe('resolvePdpGalleryMediaFromPdpState', () => {
  it('uses primary product media on a fresh PDP before selection', () => {
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      videos: [],
      configuration,
      configurationLoading: false,
      selections: {},
    });
    expect(slides.map((slide) => slide.kind === 'image' && slide.image.url)).toEqual([
      'https://cdn.example/product.jpg',
    ]);
  });

  it('selecting a variant with image resolves target media', () => {
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      videos: [],
      configuration,
      configurationLoading: false,
      selections: { 'attr-color': 'val-red' },
    });
    expect(slides[0]).toMatchObject({
      kind: 'image',
      image: { url: 'https://cdn.example/red.jpg' },
    });
    expect(slides[1]).toMatchObject({
      kind: 'image',
      image: { url: 'https://cdn.example/red-2.jpg' },
    });
  });

  it('does not reset to primary while configuration is refetching', () => {
    const loadingConfiguration: ProductConfiguration = {
      ...configuration,
      isComplete: false,
      matchedConfigurationId: null,
    };
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      videos: [],
      configuration: loadingConfiguration,
      configurationLoading: true,
      selections: { 'attr-color': 'val-blue' },
    });
    expect(slides[0]?.kind === 'image' && slides[0].image.url).toBe(
      'https://cdn.example/blue.jpg',
    );
    expect(slides[0]?.kind === 'image' && slides[0].image.url).not.toBe(
      'https://cdn.example/product.jpg',
    );
  });

  it('switches A → B without routing through product primary', () => {
    const red = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      configuration,
      configurationLoading: true,
      selections: { 'attr-color': 'val-red' },
    });
    const blue = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      configuration,
      configurationLoading: true,
      selections: { 'attr-color': 'val-blue' },
    });
    expect(red[0]?.kind === 'image' && red[0].image.url).toBe(
      'https://cdn.example/red.jpg',
    );
    expect(blue[0]?.kind === 'image' && blue[0].image.url).toBe(
      'https://cdn.example/blue.jpg',
    );
    expect(blue[0]?.kind === 'image' && blue[0].image.url).not.toBe(
      'https://cdn.example/product.jpg',
    );
  });

  it('returns to A after A → B → A without using primary as an intermediate', () => {
    const again = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      configuration,
      configurationLoading: true,
      selections: { 'attr-color': 'val-red' },
    });
    expect(again[0]?.kind === 'image' && again[0].image.url).toBe(
      'https://cdn.example/red.jpg',
    );
  });

  it('falls back to product gallery when the variant has no media', () => {
    const bare: CatalogProductVariant[] = [
      { id: 'cfg-red-s', price: 1000, images: [], primaryImageUrl: null },
    ];
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants: bare,
      configuration,
      configurationLoading: false,
      selections: { 'attr-color': 'val-red' },
    });
    expect(slides[0]?.kind === 'image' && slides[0].image.url).toBe(
      'https://cdn.example/product.jpg',
    );
  });

  it('keeps simple products on the product gallery', () => {
    const simple: ProductConfiguration = {
      ...configuration,
      hasConfigurations: false,
      isComplete: true,
      attributes: [],
      configurations: [],
    };
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants: [],
      configuration: simple,
      configurationLoading: false,
      selections: {},
    });
    expect(slides[0]?.kind === 'image' && slides[0].image.url).toBe(
      'https://cdn.example/product.jpg',
    );
  });

  it('preserves variant gallery image order', () => {
    const slides = resolvePdpGalleryMediaFromPdpState({
      productImages,
      variants,
      configuration,
      configurationLoading: false,
      selections: { 'attr-color': 'val-red' },
    });
    expect(
      slides
        .filter((slide) => slide.kind === 'image')
        .map((slide) => slide.image.url),
    ).toEqual([
      'https://cdn.example/red.jpg',
      'https://cdn.example/red-2.jpg',
    ]);
  });
});

describe('held gallery transition', () => {
  const primary = [imageSlide('https://cdn.example/product.jpg', 'p')];
  const red = [imageSlide('https://cdn.example/red.jpg', 'r')];
  const blue = [imageSlide('https://cdn.example/blue.jpg', 'b')];
  const green = [imageSlide('https://cdn.example/green.jpg', 'g')];

  it('holds current media until the target first frame is ready', () => {
    const initial = createHeldGalleryState(primary);
    const planned = planHeldGalleryUpdate(initial, red);
    expect(planned.action).toBe('prefetch');
    expect(planned.state.committed).toEqual(primary);
    expect(planned.urls).toEqual(['https://cdn.example/red.jpg']);
    const ready = applyHeldGalleryPrefetchResult(
      planned.state,
      planned.generation,
      true,
      red,
    );
    expect(galleryImageIdentity(ready.committed)).toBe(
      galleryImageIdentity(red),
    );
  });

  it('treats a cached target as an immediate commit once prefetch succeeds', () => {
    const afterRed = applyHeldGalleryPrefetchResult(
      planHeldGalleryUpdate(createHeldGalleryState(primary), red).state,
      1,
      true,
      red,
    );
    const back = planHeldGalleryUpdate(afterRed, red);
    expect(back.action).toBe('commit');
    expect(galleryImageIdentity(back.state.committed)).toBe(
      galleryImageIdentity(red),
    );
  });

  it('keeps usable media when prefetch fails', () => {
    const planned = planHeldGalleryUpdate(createHeldGalleryState(red), blue);
    const failed = applyHeldGalleryPrefetchResult(
      planned.state,
      planned.generation,
      false,
      blue,
    );
    expect(galleryImageIdentity(failed.committed)).toBe(
      galleryImageIdentity(red),
    );
  });

  it('lets C win when A → B → C prefetches finish out of order', () => {
    let state = createHeldGalleryState(primary);
    const startA = planHeldGalleryUpdate(state, red);
    state = startA.state;
    const startB = planHeldGalleryUpdate(state, blue);
    state = startB.state;
    const startC = planHeldGalleryUpdate(state, green);
    state = startC.state;

    state = applyHeldGalleryPrefetchResult(state, startA.generation, true, red);
    expect(galleryImageIdentity(state.committed)).toBe(
      galleryImageIdentity(primary),
    );
    state = applyHeldGalleryPrefetchResult(state, startB.generation, true, blue);
    expect(galleryImageIdentity(state.committed)).toBe(
      galleryImageIdentity(primary),
    );
    state = applyHeldGalleryPrefetchResult(
      state,
      startC.generation,
      true,
      green,
    );
    expect(galleryImageIdentity(state.committed)).toBe(
      galleryImageIdentity(green),
    );
  });
});

describe('bounded variant prefetch', () => {
  it('skips duplicate URLs and respects the bound', () => {
    const many: CatalogProductVariant[] = Array.from({ length: 20 }, (_, index) => ({
      id: `cfg-${index}`,
      price: 1,
      images: [
        {
          url:
            index < 12
              ? `https://cdn.example/color-${index}.jpg`
              : 'https://cdn.example/color-0.jpg',
        },
      ],
    }));
    const urls = collectPdpVariantPrefetchUrls({ variants: many });
    expect(urls).toHaveLength(PDP_VARIANT_MEDIA_PREFETCH_LIMIT);
    expect(new Set(urls).size).toBe(urls.length);
    expect(urls[0]).toBe('https://cdn.example/color-0.jpg');
  });

  it('prefers one first-frame URL per visual option', () => {
    const urls = collectPdpVariantPrefetchUrls({
      variants,
      configurations,
      attributes: [colorAttr, sizeAttr],
    });
    expect(urls).toEqual([
      'https://cdn.example/red.jpg',
      'https://cdn.example/blue.jpg',
      'https://cdn.example/green.jpg',
    ]);
  });

  it('reuses the Wave 6 memory-disk cache policy for stable PDP URLs', () => {
    expect(PDP_GALLERY_CACHE_POLICY).toBe(LIST_IMAGE_CACHE_POLICY);
    expect(pdpGalleryImageProps('https://cdn.example/red.jpg')).toEqual({
      cachePolicy: 'memory-disk',
      recyclingKey: 'https://cdn.example/red.jpg',
    });
    expect(firstImageSlideUrls([imageSlide('https://cdn.example/red.jpg')])).toEqual([
      'https://cdn.example/red.jpg',
    ]);
  });
});

describe('brand metadata and commercial layers stay separate', () => {
  it('keeps ZION MODE brand metadata on mapped PDP payloads', () => {
    const detail = mapProductDetail({
      id: 'p-zion',
      slug: 'essential-knit-top',
      name: 'ESSENTIAL KNIT TOP',
      price: 25000,
      brand: { id: 'b1', slug: 'zion-mode', name: 'ZION MODE' },
      primary_image: { url: 'https://cdn.example/product.jpg' },
      images: [{ url: 'https://cdn.example/product.jpg' }],
      variants: [
        {
          id: 'cfg-red-s',
          price: 25000,
          images: [{ url: 'https://cdn.example/red.jpg' }],
        },
      ],
    });
    expect(detail?.brand).toEqual({
      id: 'b1',
      slug: 'zion-mode',
      name: 'ZION MODE',
    });
    expect(detail?.name).toBe('ESSENTIAL KNIT TOP');
  });
});
