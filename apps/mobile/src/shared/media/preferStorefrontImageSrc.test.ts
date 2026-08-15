import {
  preferStorefrontImageSrc,
  preferStorefrontImageSrcFromUnknown,
} from './preferStorefrontImageSrc';

describe('preferStorefrontImageSrc', () => {
  it('prefers display_url over url and path', () => {
    expect(
      preferStorefrontImageSrc({
        display_url: 'https://cdn.example/display.webp',
        url: 'https://cdn.example/original.png',
        path: 'products/original.png',
      }),
    ).toBe('https://cdn.example/display.webp');
  });

  it('falls back to url when display_url missing or empty', () => {
    expect(
      preferStorefrontImageSrc({
        display_url: null,
        url: 'https://cdn.example/original.png',
        path: 'products/original.png',
      }),
    ).toBe('https://cdn.example/original.png');

    expect(
      preferStorefrontImageSrc({
        display_url: '   ',
        url: 'https://cdn.example/original.png',
        path: 'products/original.png',
      }),
    ).toBe('https://cdn.example/original.png');
  });

  it('falls back to path when display_url and url missing', () => {
    expect(
      preferStorefrontImageSrc({
        display_url: null,
        url: null,
        path: 'products/original.png',
      }),
    ).toBe('products/original.png');
  });

  it('ignores original_url for normal display selection', () => {
    expect(
      preferStorefrontImageSrc({
        display_url: null,
        original_url: 'https://cdn.example/master.png',
        url: 'https://cdn.example/display-or-url.png',
        path: 'products/x.png',
      }),
    ).toBe('https://cdn.example/display-or-url.png');
  });

  it('returns null for empty payloads', () => {
    expect(preferStorefrontImageSrc(null)).toBeNull();
    expect(preferStorefrontImageSrc({})).toBeNull();
  });
});

describe('preferStorefrontImageSrcFromUnknown', () => {
  it('reads additive API fields from unknown media objects', () => {
    expect(
      preferStorefrontImageSrcFromUnknown({
        display_url: '/storage/products/storefront/a.webp',
        url: '/storage/products/a.jpg',
        path: 'products/a.jpg',
      }),
    ).toBe('/storage/products/storefront/a.webp');
  });

  it('preserves older payloads without display_url', () => {
    expect(
      preferStorefrontImageSrcFromUnknown({
        url: 'https://cdn.example/legacy.jpg',
      }),
    ).toBe('https://cdn.example/legacy.jpg');
  });
});
