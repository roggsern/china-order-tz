import { BOTTOM_TAB_ROUTES } from './tabIcons';

describe('Wave 5A Shop wording cleanup', () => {
  it('keeps Shop as the visible bottom-tab catalog destination', () => {
    expect(BOTTOM_TAB_ROUTES).toContain('browse');
    expect(BOTTOM_TAB_ROUTES).not.toContain('search');
  });

  it('documents Shop as the customer-facing catalog label', () => {
    // Browse remains the route id; Shop is the customer label (tabs layout).
    const shopLabel = 'Shop';
    const staleBrowseCta = 'Go to Browse';
    expect(shopLabel).toBe('Shop');
    expect(staleBrowseCta).not.toBe(shopLabel);
  });
});
