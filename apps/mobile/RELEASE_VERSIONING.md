# EAS / app version ownership

- Semantic version (`expo.version`): owned by `app.json` — currently **0.1.0**
- Android `versionCode`: owned by `app.json` — currently **3** (not auto-bumped)
- iOS `CFBundleVersion` (`ios.buildNumber`): owned by `app.json` — currently **"3"**
- `eas.json` `cli.appVersionSource`: **local**

## First store / TestFlight recommendation

Keep marketing version **0.1.0** for the first internal Play track and first TestFlight.
Do not publish **1.0.0** until product/owner policy explicitly asks for a public 1.0.

Bump `android.versionCode` and `ios.buildNumber` together for every store/TestFlight
binary that reuses the same marketing version.

Do not enable EAS `autoIncrement` until Android and iOS versioning policies are
intentionally unified.
