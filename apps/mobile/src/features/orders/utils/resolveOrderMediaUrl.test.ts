import {
  resolveApiOrigin,
  resolveOrderMediaUrl,
} from './resolveOrderMediaUrl';

describe('resolveOrderMediaUrl', () => {
  const apiBase = 'https://api.chinaordertz.com/api/v1';

  it('derives Laravel public origin by stripping /api/v1', () => {
    expect(resolveApiOrigin(apiBase)).toBe('https://api.chinaordertz.com');
    expect(resolveApiOrigin('https://api.example.com/api')).toBe(
      'https://api.example.com',
    );
  });

  it('passes through absolute https snapshot urls', () => {
    expect(
      resolveOrderMediaUrl('https://cdn.example/gown.jpg', apiBase),
    ).toBe('https://cdn.example/gown.jpg');
  });

  it('absolutizes production-shaped /storage relative paths (web parity)', () => {
    expect(
      resolveOrderMediaUrl('/storage/products/blouse.jpg', apiBase),
    ).toBe('https://api.chinaordertz.com/storage/products/blouse.jpg');
    expect(
      resolveOrderMediaUrl('storage/products/blouse.jpg', apiBase),
    ).toBe('https://api.chinaordertz.com/storage/products/blouse.jpg');
    expect(
      resolveOrderMediaUrl('products/blouse.jpg', apiBase),
    ).toBe('https://api.chinaordertz.com/storage/products/blouse.jpg');
    expect(
      resolveOrderMediaUrl('demo-products/phone.jpg', apiBase),
    ).toBe('https://api.chinaordertz.com/storage/demo-products/phone.jpg');
  });

  it('returns null for empty media without inventing product images', () => {
    expect(resolveOrderMediaUrl(null, apiBase)).toBeNull();
    expect(resolveOrderMediaUrl('   ', apiBase)).toBeNull();
  });
});
