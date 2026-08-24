import { Image } from 'expo-image';
import { prefetchPdpVariantMedia } from './prefetchPdpVariantMedia';

jest.mock('expo-image', () => ({
  Image: {
    prefetch: jest.fn(),
  },
}));

describe('prefetchPdpVariantMedia', () => {
  it('no-ops successfully for an empty list', async () => {
    await expect(prefetchPdpVariantMedia([])).resolves.toBe(true);
    expect(Image.prefetch).not.toHaveBeenCalled();
  });

  it('deduplicates URLs and uses memory-disk cache', async () => {
    (Image.prefetch as jest.Mock).mockResolvedValue(true);
    await expect(
      prefetchPdpVariantMedia([
        'https://cdn.example/red.jpg',
        'https://cdn.example/red.jpg',
        ' https://cdn.example/blue.jpg ',
      ]),
    ).resolves.toBe(true);
    expect(Image.prefetch).toHaveBeenCalledWith(
      ['https://cdn.example/red.jpg', 'https://cdn.example/blue.jpg'],
      'memory-disk',
    );
  });

  it('treats prefetch exceptions as failure so callers keep fallback media', async () => {
    (Image.prefetch as jest.Mock).mockRejectedValue(new Error('network'));
    await expect(
      prefetchPdpVariantMedia(['https://cdn.example/red.jpg']),
    ).resolves.toBe(false);
  });
});
