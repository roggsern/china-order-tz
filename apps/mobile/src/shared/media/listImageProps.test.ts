import { LIST_IMAGE_CACHE_POLICY, listImageProps } from './listImageProps';

describe('listImageProps', () => {
  it('uses Expo memory-disk cache and a stable recycling key', () => {
    expect(listImageProps('https://cdn.example/p.jpg')).toEqual({
      cachePolicy: LIST_IMAGE_CACHE_POLICY,
      recyclingKey: 'https://cdn.example/p.jpg',
    });
    expect(LIST_IMAGE_CACHE_POLICY).toBe('memory-disk');
  });
});
