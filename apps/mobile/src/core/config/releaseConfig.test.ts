import appJson from '../../../app.json';
import easJson from '../../../eas.json';

describe('release config', () => {
  it('keeps a single Android/iOS identity and chinaordertz scheme', () => {
    expect(appJson.expo.android.package).toBe('com.chinaordertz.mobile');
    expect(appJson.expo.ios.bundleIdentifier).toBe('com.chinaordertz.mobile');
    expect(appJson.expo.scheme).toBe('chinaordertz');
    expect(appJson.expo.version).toBe('0.1.0');
    expect(appJson.expo.android.versionCode).toBe(2);
    expect(appJson.expo.ios.buildNumber).toBe('2');
  });

  it('declares App Store encryption as exempt / standard encryption only', () => {
    expect(appJson.expo.ios.infoPlist.ITSAppUsesNonExemptEncryption).toBe(false);
  });

  it('points extra config at the production API and storefront', () => {
    expect(appJson.expo.extra.apiBaseUrl).toBe('https://api.chinaordertz.com/api/v1');
    expect(appJson.expo.extra.webAppBaseUrl).toBe('https://chinaordertz.com');
  });

  it('defines development, preview, and production EAS profiles', () => {
    expect(easJson.build.development.developmentClient).toBe(true);
    expect(easJson.build.development.distribution).toBe('internal');
    expect(easJson.build.preview.android.buildType).toBe('apk');
    expect(easJson.build.production.android.buildType).toBe('app-bundle');
    expect(easJson.build.development.environment).toBe('development');
    expect(easJson.build.preview.environment).toBe('preview');
    expect(easJson.build.production.environment).toBe('production');
  });

  it('keeps production builds on production API with sandbox checkout disabled', () => {
    expect(easJson.build.production.env.EXPO_PUBLIC_API_BASE_URL).toBe(
      'https://api.chinaordertz.com/api/v1',
    );
    expect(easJson.build.production.env.EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT).toBe(
      'false',
    );
    expect(easJson.build.preview.env.EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT).toBe(
      'true',
    );
  });

  it('keeps Play submit on an internal draft track', () => {
    expect(easJson.submit.production.android.track).toBe('internal');
    expect(easJson.submit.production.android.releaseStatus).toBe('draft');
  });

  it('does not enable remote EAS autoIncrement', () => {
    expect(easJson.cli.appVersionSource).toBe('local');
  });
});
