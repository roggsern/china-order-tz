# Wave 7 — store / TestFlight readiness

This document is operational, not marketing copy. It does not create store records.

## Identities

| Item | Value |
|------|--------|
| Display name | CHINA ORDER TZ |
| Android package | `com.chinaordertz.mobile` |
| iOS bundle id | `com.chinaordertz.mobile` |
| Scheme | `chinaordertz` |
| Marketing version | `0.1.0` |
| Android versionCode | `3` |
| iOS buildNumber | `3` |
| Expo SDK | 57 |
| EAS project id | `c6ec7c64-8518-4e96-8df5-ac5d5a88f545` |
| Privacy policy | https://chinaordertz.com/privacy |
| Terms | https://chinaordertz.com/terms |
| Account deletion help | https://chinaordertz.com/delete-account |
| Support email | support@chinaordertz.com |

## EAS profiles

| Profile | Use | Android | iOS | API | NMB sandbox |
|---------|-----|---------|-----|-----|-------------|
| `development` | Expo dev client | default | default | production API | allowed |
| `preview` | internal testers | APK | default | production API | allowed |
| `production` | store / TestFlight | AAB | archive | production API | **disabled** |

Submit (`eas submit --profile production`) is configured as Play **internal / draft** only.
iOS App Store Connect app id is **not** checked in — set after the ASC record exists.

## Secret names only (never commit values)

| Name | Kind | Where |
|------|------|--------|
| `GOOGLE_SERVICES_JSON` | EAS file env | Android FCM client config |
| `EXPO_PUBLIC_API_BASE_URL` | public env | API origin (`/api/v1`) |
| `EXPO_PUBLIC_WEB_APP_BASE_URL` | public env | storefront origin |
| `EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT` | public flag | preview/dev only |
| Apple Distribution cert / profile | EAS credentials | iOS signing |
| APNs key | Apple / EAS | iOS push |
| Play upload keystore | EAS credentials | Android signing |

Mobile must never contain NMB/Snippe provider secrets or Laravel private keys.

## Apple membership — later steps (do not perform here)

1. Confirm Apple Developer Program membership is **Active**.
2. Register App ID `com.chinaordertz.mobile`.
3. Enable Push Notifications capability.
4. Create APNs key and upload to EAS.
5. Create/assign EAS iOS credentials (distribution cert + profile).
6. Create App Store Connect app record.
7. Upload a production iOS archive to TestFlight.
8. Internal testing.
9. External testing if desired.
10. App Store submission.

## Android later steps (do not perform here)

1. Confirm Play Console app for `com.chinaordertz.mobile`.
2. Confirm EAS Android keystore (or Play App Signing).
3. Create `GOOGLE_SERVICES_JSON` EAS file env, then untrack repo copy.
4. Build production AAB (`eas build --profile production --platform android`).
5. Upload to internal testing track as **draft**.
6. Complete Data safety, content rating, store listing assets.

## Real-device acceptance (both OS)

Use low-value controlled test orders. Do not repeat live charges unnecessarily.

### AUTH
- [ ] Register and login
- [ ] Session restore after kill
- [ ] Logout clears session and push registration

### CATALOG
- [ ] China import browse
- [ ] Tanzania browse
- [ ] Search
- [ ] PDP variants show the correct variant image

### CART / CHECKOUT
- [ ] Add / qty / remove
- [ ] Clear cart
- [ ] Start checkout session
- [ ] Save shipping / delivery choice
- [ ] Cancel leftover checkout safely

### PAYMENTS
- [ ] Method selector matches server availability
- [ ] NMB hosted checkout + `chinaordertz://payment-return`
- [ ] Snippe success
- [ ] Snippe abandon / recover (no second collection)
- [ ] Pay at Office
- [ ] Pay Now from an unpaid order
- [ ] Cancelled/refunded order cannot pay

### ORDERS
- [ ] List + detail lifecycle copy
- [ ] Tracking
- [ ] Receiving choice after arrival
- [ ] Delivery option when eligible
- [ ] Return request after delivered/completed

### PUSH
- [ ] Token registers on a physical device
- [ ] Foreground banner, no auto-navigation
- [ ] Background tap opens the correct screen
- [ ] Cold-start tap consumed once
- [ ] Logout deactivates this installation token

### DEEP LINKS
- [ ] `chinaordertz://payment-return` warm and cold
- [ ] Logged-out `returnTo` stays in-app (`/(app)/…` only)

### PERFORMANCE
- [ ] Startup splash then home
- [ ] Catalog/search list scroll
- [ ] Images reuse cache after revisit
- [ ] App resume refreshes payment/order/cart, not the whole catalog
