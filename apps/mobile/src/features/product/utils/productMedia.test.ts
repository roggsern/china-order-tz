import {
  buildProductVideoEmbedHtml,
  isSupportedProductVideoUrl,
  resolveProductVideoEmbedUrl,
  resolveProductVideoExternalUrl,
  resolveProductVideoProvider,
  resolveProductVideoThumbnailUrl,
  youtubeVideoId,
} from './productVideo';
import { resolveProductGalleryImageFit } from './productGalleryFit';
import { resolvePdpGalleryMedia } from './resolvePdpGalleryMedia';

describe('productGalleryFit', () => {
  it('keeps deterministic contain fit in the 1:1 PDP frame', () => {
    expect(resolveProductGalleryImageFit()).toBe('contain');
    expect(resolveProductGalleryImageFit({ sourceAspectRatio: 1.5 })).toBe(
      'contain',
    );
  });
});

describe('productVideo', () => {
  it('accepts YouTube and Vimeo only', () => {
    expect(
      isSupportedProductVideoUrl(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ),
    ).toBe(true);
    expect(isSupportedProductVideoUrl('https://vimeo.com/123456789')).toBe(true);
    expect(isSupportedProductVideoUrl('https://example.com/a.mp4')).toBe(false);
    expect(resolveProductVideoProvider('https://youtu.be/dQw4w9WgXcQ')).toBe(
      'youtube',
    );
  });

  it('builds safe nocookie embed urls with optional origin for Error 153', () => {
    const embed = resolveProductVideoEmbedUrl(
      'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      { origin: 'https://chinaordertz.com' },
    );
    expect(embed).toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');
    expect(embed).toContain('origin=https%3A%2F%2Fchinaordertz.com');
    expect(resolveProductVideoEmbedUrl('https://evil.example/x')).toBeNull();
    expect(youtubeVideoId('https://youtu.be/abcDEF12345')).toBe('abcDEF12345');
    expect(
      resolveProductVideoExternalUrl(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ),
    ).toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  });

  it('builds embed HTML with strict-origin referrer policy (no admin HTML)', () => {
    const html = buildProductVideoEmbedHtml({
      embedUrl: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?playsinline=1',
      title: 'Demo',
    });
    expect(html).toContain('referrerpolicy="strict-origin-when-cross-origin"');
    expect(html).toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');
    expect(html).not.toContain('<script');
  });

  it('prefers provided thumbnail then YouTube hqdefault', () => {
    expect(
      resolveProductVideoThumbnailUrl({
        url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        thumbnailUrl: 'https://cdn.example/custom.jpg',
      }),
    ).toBe('https://cdn.example/custom.jpg');
    expect(
      resolveProductVideoThumbnailUrl({
        url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        thumbnailUrl: null,
      }),
    ).toBe('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
    expect(
      resolveProductVideoThumbnailUrl({
        url: 'https://vimeo.com/123',
        thumbnailUrl: null,
      }),
    ).toBeNull();
  });
});

describe('resolvePdpGalleryMedia', () => {
  it('preserves matched variant gallery precedence then appends videos', () => {
    const slides = resolvePdpGalleryMedia({
      productImages: [{ id: 'p', url: 'https://cdn.example/product.jpg' }],
      variants: [
        {
          id: 'cfg-1',
          price: 10,
          images: [{ id: 'v', url: 'https://cdn.example/variant.jpg' }],
        },
      ],
      matchedConfigurationId: 'cfg-1',
      mediaPreviewConfigurationId: null,
      videos: [
        {
          id: 'vid-1',
          url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
          thumbnailUrl: null,
          title: 'Demo',
          altText: null,
          sortOrder: 1,
        },
      ],
    });

    expect(slides[0]).toMatchObject({
      kind: 'image',
      image: { url: 'https://cdn.example/variant.jpg' },
    });
    expect(slides[1]?.kind).toBe('video');
  });

  it('uses partial preview when exact match has no media', () => {
    const slides = resolvePdpGalleryMedia({
      productImages: [{ url: 'https://cdn.example/product.jpg' }],
      variants: [
        {
          id: 'preview-1',
          price: 10,
          images: [{ url: 'https://cdn.example/preview.jpg' }],
        },
      ],
      matchedConfigurationId: null,
      mediaPreviewConfigurationId: 'preview-1',
      videos: [],
    });
    expect(slides).toEqual([
      expect.objectContaining({
        kind: 'image',
        image: { url: 'https://cdn.example/preview.jpg' },
      }),
    ]);
  });

  it('returns empty slides for missing images and drops unsupported videos', () => {
    const slides = resolvePdpGalleryMedia({
      productImages: [],
      variants: [],
      videos: [
        {
          id: 'bad',
          url: 'https://example.com/x.mp4',
          thumbnailUrl: null,
          title: null,
          altText: null,
          sortOrder: 0,
        },
      ],
    });
    expect(slides).toEqual([]);
  });
});
