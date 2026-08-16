/**
 * EAS / app version ownership (MOBILE-RELEASE-01):
 *
 * - Semantic version (`expo.version`): owned by app.json — currently 0.1.0
 * - Android versionCode: owned by app.json — currently 2 (not auto-bumped here)
 * - iOS CFBundleVersion (`ios.buildNumber`): owned by app.json — currently "2"
 * - appVersionSource: local (eas.json cli.appVersionSource)
 *
 * Bump ios.buildNumber for every App Store / TestFlight upload that reuses the
 * same marketing version. Do not enable EAS autoIncrement until Android and iOS
 * versioning policies are intentionally unified.
 */
