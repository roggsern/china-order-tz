import {
  BRAND_NAME,
  BRAND_TAGLINE,
  FUTURE_BRANDING_FILES,
  OFFICIAL_BRANDING_FILES,
  brandAssetPaths,
} from './assets';

describe('branding registry', () => {
  it('exposes the product brand name and tagline', () => {
    expect(BRAND_NAME).toBe('CHINA ORDER TZ');
    expect(BRAND_TAGLINE.length).toBeGreaterThan(10);
  });

  it('registers official branding image modules', () => {
    expect(brandAssetPaths.logoHeader).toBeDefined();
    expect(brandAssetPaths.logoMark).toBeDefined();
    expect(brandAssetPaths.splashBrand).toBeDefined();
    expect(brandAssetPaths.splashBrandSafe).toBeDefined();
    expect(brandAssetPaths.appIcon).toBeDefined();
  });

  it('keeps stable on-disk paths for store / EAS tooling', () => {
    expect(OFFICIAL_BRANDING_FILES.logoHeader).toBe(
      'assets/branding/logo-header.png',
    );
    expect(OFFICIAL_BRANDING_FILES.splashBrandSafe).toBe(
      'assets/branding/splash-brand-safe.png',
    );
    expect(OFFICIAL_BRANDING_FILES.appIconSource).toContain('app-icon.png');
    expect(FUTURE_BRANDING_FILES).toEqual(OFFICIAL_BRANDING_FILES);
  });
});
