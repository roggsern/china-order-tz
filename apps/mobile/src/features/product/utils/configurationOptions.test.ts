import {
  filterVisibleConfigurationValues,
  pruneUnassignedConfigurationSelections,
  resolvePdpGalleryImages,
  toggleConfigurationSelection,
} from './configurationOptions';
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
  isRequired: true,
  participatesInConfiguration: true,
  values: [
    { id: 'val-black', value: 'Black' },
    { id: 'val-white', value: 'White' },
    { id: 'val-red', value: 'Red' },
    { id: 'val-kimono', value: 'Kimono Sunrise' },
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
    { id: 'val-l', value: 'L' },
  ],
};

const configurations: ProductConfigurationRow[] = [
  {
    id: 'cfg-black-m',
    attributeValueIds: ['val-black', 'val-m'],
    inStock: true,
  },
  {
    id: 'cfg-white-l',
    attributeValueIds: ['val-white', 'val-l'],
    inStock: true,
  },
];

describe('filterVisibleConfigurationValues', () => {
  it('hides schema values not assigned to any product configuration', () => {
    const visible = filterVisibleConfigurationValues(colorAttr, configurations);
    expect(visible.map((value) => value.id)).toEqual(['val-black', 'val-white']);
    expect(visible.map((value) => value.value)).not.toContain('Red');
    expect(visible.map((value) => value.value)).not.toContain('Kimono Sunrise');
  });

  it('shows only assigned size values for multi-attribute products', () => {
    const visible = filterVisibleConfigurationValues(sizeAttr, configurations);
    expect(visible.map((value) => value.id)).toEqual(['val-m', 'val-l']);
    expect(visible.map((value) => value.id)).not.toContain('val-s');
  });
});

describe('toggleConfigurationSelection', () => {
  it('selects an unselected value', () => {
    expect(toggleConfigurationSelection({}, 'attr-color', 'val-black')).toEqual({
      'attr-color': 'val-black',
    });
  });

  it('deselects the currently selected value', () => {
    expect(
      toggleConfigurationSelection(
        { 'attr-color': 'val-black' },
        'attr-color',
        'val-black',
      ),
    ).toEqual({});
  });

  it('switches to another value on the same attribute', () => {
    expect(
      toggleConfigurationSelection(
        { 'attr-color': 'val-black', 'attr-size': 'val-m' },
        'attr-color',
        'val-white',
      ),
    ).toEqual({
      'attr-color': 'val-white',
      'attr-size': 'val-m',
    });
  });
});

describe('pruneUnassignedConfigurationSelections', () => {
  it('keeps cascade-disallowed but product-assigned selections', () => {
    const pruned = pruneUnassignedConfigurationSelections(
      { 'attr-color': 'val-black', 'attr-size': 'val-m' },
      {
        configurations,
        attributes: [colorAttr, sizeAttr],
      },
    );
    expect(pruned).toEqual({
      'attr-color': 'val-black',
      'attr-size': 'val-m',
    });
  });

  it('drops selections that are not on any configuration row', () => {
    const pruned = pruneUnassignedConfigurationSelections(
      { 'attr-color': 'val-kimono', 'attr-size': 'val-m' },
      {
        configurations,
        attributes: [colorAttr, sizeAttr],
      },
    );
    expect(pruned).toEqual({ 'attr-size': 'val-m' });
  });
});

describe('resolvePdpGalleryImages', () => {
  const productImages: CatalogImage[] = [
    { id: 'p-img', url: 'https://cdn.example/product.jpg' },
  ];

  it('uses matched variant gallery images when present', () => {
    const variants: CatalogProductVariant[] = [
      {
        id: 'cfg-1',
        price: 1,
        images: [{ id: 'v1', url: 'https://cdn.example/variant.jpg' }],
        primaryImageUrl: 'https://cdn.example/primary.jpg',
      },
    ];
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: 'cfg-1',
      }),
    ).toEqual([{ id: 'v1', url: 'https://cdn.example/variant.jpg' }]);
  });

  it('falls back to variant primary_image when gallery empty', () => {
    const variants: CatalogProductVariant[] = [
      {
        id: 'cfg-1',
        price: 1,
        images: [],
        primaryImageUrl: 'https://cdn.example/primary.jpg',
      },
    ];
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: 'cfg-1',
      }),
    ).toEqual([{ url: 'https://cdn.example/primary.jpg' }]);
  });

  it('falls back to product gallery when variant has no media', () => {
    const variants: CatalogProductVariant[] = [
      { id: 'cfg-1', price: 1, images: [], primaryImageUrl: null },
    ];
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: 'cfg-1',
      }),
    ).toEqual(productImages);
  });

  it('returns product gallery when selection cleared / no match', () => {
    const variants: CatalogProductVariant[] = [
      {
        id: 'cfg-1',
        price: 1,
        images: [{ url: 'https://cdn.example/variant.jpg' }],
      },
    ];
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: null,
      }),
    ).toEqual(productImages);
  });

  it('switches gallery when matched configuration changes', () => {
    const variants: CatalogProductVariant[] = [
      {
        id: 'cfg-1',
        price: 1,
        images: [{ url: 'https://cdn.example/a.jpg' }],
      },
      {
        id: 'cfg-2',
        price: 1,
        images: [{ url: 'https://cdn.example/b.jpg' }],
      },
    ];
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: 'cfg-1',
      })[0]?.url,
    ).toBe('https://cdn.example/a.jpg');
    expect(
      resolvePdpGalleryImages({
        productImages,
        variants,
        matchedConfigurationId: 'cfg-2',
      })[0]?.url,
    ).toBe('https://cdn.example/b.jpg');
  });
});
