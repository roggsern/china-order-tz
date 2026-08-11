import { resolveHomepageProductJourney } from './resolveHomepageProductJourney';

describe('resolveHomepageProductJourney', () => {
  it('routes China product by product identity even if UI is TZ', () => {
    expect(
      resolveHomepageProductJourney({ commerceChannelCode: 'CHINA_IMPORT' }),
    ).toBe('CHINA_IMPORT');
  });

  it('requires TZ channel for TZ products', () => {
    expect(
      resolveHomepageProductJourney({ commerceChannelCode: 'TZ_LOCAL' }),
    ).toBe('TZ_LOCAL');
  });

  it('ambiguous product identity does not misroute', () => {
    expect(resolveHomepageProductJourney({ commerceChannelCode: null })).toBeNull();
    expect(resolveHomepageProductJourney({ commerceChannelCode: 'GLOBAL' })).toBeNull();
    expect(resolveHomepageProductJourney({})).toBeNull();
  });
});
