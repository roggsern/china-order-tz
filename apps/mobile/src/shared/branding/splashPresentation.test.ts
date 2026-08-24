import fs from 'fs';
import path from 'path';
import appJson from '../../../app.json';
import easJson from '../../../eas.json';
import {
  BRAND_NAME,
  OFFICIAL_BRANDING_FILES,
  brandAssetPaths,
} from './assets';
import { resolveBrandMarkLayout } from './BrandMark';
import {
  SPLASH_BACKGROUND_COLOR,
  SPLASH_CANONICAL_ASSET_PATH,
  SPLASH_NATIVE_IMAGE_WIDTH,
  SPLASH_RESIZE_MODE,
  SPLASH_SAFE_ASSET_PATH,
  SPLASH_SAFE_CANVAS_PX,
  SPLASH_SAFE_PLUGIN_IMAGE,
  SPLASH_VIEW_MARK_SIZE,
} from './splashPresentation';

function splashPluginConfig(): {
  image?: string;
  imageWidth?: number;
  resizeMode?: string;
  backgroundColor?: string;
} {
  const entry = appJson.expo.plugins.find(
    (plugin) => Array.isArray(plugin) && plugin[0] === 'expo-splash-screen',
  );
  if (!Array.isArray(entry) || typeof entry[1] !== 'object' || entry[1] === null) {
    throw new Error('expo-splash-screen plugin config missing');
  }
  return entry[1] as {
    image?: string;
    imageWidth?: number;
    resizeMode?: string;
    backgroundColor?: string;
  };
}

describe('native and React splash presentation', () => {
  it('points splash at a dedicated safe lockup, not the app icon', () => {
    const splash = splashPluginConfig();
    expect(splash.image).toBe(SPLASH_SAFE_PLUGIN_IMAGE);
    expect(splash.image).not.toContain('app-icon');
    expect(OFFICIAL_BRANDING_FILES.splashBrandSafe).toBe(SPLASH_SAFE_ASSET_PATH);
    expect(OFFICIAL_BRANDING_FILES.splashBrand).toBe(SPLASH_CANONICAL_ASSET_PATH);
    expect(brandAssetPaths.splashBrandSafe).toBeDefined();
    expect(brandAssetPaths.splashBrand).toBeDefined();
    expect(brandAssetPaths.appIcon).toBeDefined();
    expect(brandAssetPaths.splashBrandSafe).not.toBe(brandAssetPaths.appIcon);
  });

  it('uses contain sizing and the cream splash background', () => {
    const splash = splashPluginConfig();
    expect(splash.resizeMode).toBe(SPLASH_RESIZE_MODE);
    expect(splash.resizeMode).toBe('contain');
    expect(splash.resizeMode).not.toBe('cover');
    expect(splash.backgroundColor).toBe(SPLASH_BACKGROUND_COLOR);
    expect(splash.imageWidth).toBe(SPLASH_NATIVE_IMAGE_WIDTH);
  });

  it('keeps the derived splash-safe asset as a padded square', async () => {
    const filePath = path.join(__dirname, '../../../', SPLASH_SAFE_ASSET_PATH);
    expect(fs.existsSync(filePath)).toBe(true);
    expect(fs.existsSync(path.join(__dirname, '../../../', SPLASH_CANONICAL_ASSET_PATH))).toBe(
      true,
    );
    expect(SPLASH_SAFE_CANVAS_PX).toBe(2048);
    expect(SPLASH_VIEW_MARK_SIZE).toBe(SPLASH_NATIVE_IMAGE_WIDTH);

    // sharp is a native rasterizer used by mobile asset scripts; Jest CJS cannot dynamic-import it.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const sharp = require('sharp') as typeof import('sharp');
    const meta = await sharp(filePath).metadata();
    expect(meta.width).toBe(SPLASH_SAFE_CANVAS_PX);
    expect(meta.height).toBe(SPLASH_SAFE_CANVAS_PX);
    expect(meta.hasAlpha).toBe(true);

    const canonical = await sharp(
      path.join(__dirname, '../../../', SPLASH_CANONICAL_ASSET_PATH),
    ).metadata();
    expect(canonical.width).toBe(973);
    expect(canonical.height).toBe(263);
  });

  it('does not crop the splash BrandMark to a short wide box', () => {
    const splash = resolveBrandMarkLayout('splash', SPLASH_VIEW_MARK_SIZE);
    expect(splash.width).toBe(SPLASH_VIEW_MARK_SIZE);
    expect(splash.height).toBe(SPLASH_VIEW_MARK_SIZE);
    expect(splash.resizeMode).toBe('contain');
    expect(splash.overflow).toBe('visible');

    const header = resolveBrandMarkLayout('header', 40);
    expect(header.width).toBeGreaterThan(header.height);
    expect(header.overflow).toBe('hidden');
  });

  it('does not change app name, package, bundle, or production release config', () => {
    expect(BRAND_NAME).toBe('CHINA ORDER TZ');
    expect(appJson.expo.name).toBe('CHINA ORDER TZ');
    expect(appJson.expo.android.package).toBe('com.chinaordertz.mobile');
    expect(appJson.expo.ios.bundleIdentifier).toBe('com.chinaordertz.mobile');
    expect(easJson.build.production.env.EXPO_PUBLIC_API_BASE_URL).toBe(
      'https://api.chinaordertz.com/api/v1',
    );
    expect(easJson.build.production.env.EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT).toBe(
      'false',
    );
  });
});
