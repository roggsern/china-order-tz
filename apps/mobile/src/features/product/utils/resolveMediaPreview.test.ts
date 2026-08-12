import {
  buildVariantGalleries,
  resolveMediaPreviewConfigurationId,
} from './resolveMediaPreview';
import { resolvePdpGalleryImages } from './configurationOptions';
import type {
  CatalogImage,
  CatalogProductVariant,
  ProductConfigurationAttribute,
  ProductConfigurationRow,
} from '../models/types';

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
    id: 'cfg-red-m',
    attributeValueIds: ['val-red', 'val-m'],
    inStock: false,
    stock: 0,
  },
  {
    id: 'cfg-blue-s',
    attributeValueIds: ['val-blue', 'val-s'],
    inStock: true,
    stock: 2,
  },
];

const redImages: CatalogImage[] = [{ url: 'https://cdn.example/red.jpg' }];
const blueImages: CatalogImage[] = [{ url: 'https://cdn.example/blue.jpg' }];
const productImages: CatalogImage[] = [{ url: 'https://cdn.example/product.jpg' }];

const variants: CatalogProductVariant[] = [
  {
    id: 'cfg-red-s',
    price: 1000,
    images: redImages,
  },
  {
    id: 'cfg-red-m',
    price: 1000,
    images: redImages,
  },
  {
    id: 'cfg-blue-s',
    price: 1000,
    images: blueImages,
  },
];

describe('resolveMediaPreviewConfigurationId', () => {
  it('previews Red media when only Color is selected', () => {
    const galleries = buildVariantGalleries(variants);
    const previewId = resolveMediaPreviewConfigurationId({
      configurations,
      selections: { 'attr-color': 'val-red' },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
      exactConfigurationId: null,
    });
    expect(previewId).toBe('cfg-red-s');
  });

  it('prefers in-stock candidate when multiple colors match', () => {
    const galleries = buildVariantGalleries(variants);
    const previewId = resolveMediaPreviewConfigurationId({
      configurations,
      selections: { 'attr-color': 'val-red' },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    expect(previewId).toBe('cfg-red-s');
  });

  it('returns null when visual attribute unset', () => {
    expect(
      resolveMediaPreviewConfigurationId({
        configurations,
        selections: { 'attr-size': 'val-s' },
        attributes: [colorAttr, sizeAttr],
        variantGalleries: buildVariantGalleries(variants),
      }),
    ).toBeNull();
  });

  it('returns null when no candidate has media', () => {
    expect(
      resolveMediaPreviewConfigurationId({
        configurations,
        selections: { 'attr-color': 'val-red' },
        attributes: [colorAttr, sizeAttr],
        variantGalleries: {},
      }),
    ).toBeNull();
  });
});

describe('resolvePdpGalleryImages with partial preview', () => {
  it('uses partial preview media before product gallery', () => {
    const images = resolvePdpGalleryImages({
      productImages,
      variants,
      matchedConfigurationId: null,
      mediaPreviewConfigurationId: 'cfg-red-s',
    });
    expect(images).toEqual(redImages);
  });

  it('full match overrides partial preview', () => {
    const images = resolvePdpGalleryImages({
      productImages,
      variants,
      matchedConfigurationId: 'cfg-blue-s',
      mediaPreviewConfigurationId: 'cfg-red-s',
    });
    expect(images).toEqual(blueImages);
  });

  it('deselect / no preview falls back to product gallery', () => {
    const images = resolvePdpGalleryImages({
      productImages,
      variants,
      matchedConfigurationId: null,
      mediaPreviewConfigurationId: null,
    });
    expect(images).toEqual(productImages);
  });
});
