# Official CHINA ORDER TZ branding assets (mobile)

Source of truth: `apps/web/public/branding/`.

## Checked-in files

| File | Use |
|------|-----|
| `logo-header.png` | Horizontal lockup (headers / splash) |
| `logo-footer.png` | Footer / dark-surface lockup |
| `logo-mark.png` | Compact mark (tab headers, favicon) |
| `splash-brand.png` | Native + in-app splash |
| `app-icon.png` | Store / Expo app icon (512px from web `icon-512.png`) |

`app.json` and `src/shared/branding/assets.ts` point at these paths.

## Refreshing assets

Copy from web (do not invent artwork):

```powershell
$web = "..\web\public\branding"
Copy-Item "$web\logo-header.png" .\logo-header.png -Force
Copy-Item "$web\logo-footer.png" .\logo-footer.png -Force
Copy-Item "$web\favicon.png" .\logo-mark.png -Force
Copy-Item "$web\logo-header.png" .\splash-brand.png -Force
Copy-Item "$web\icon-512.png" .\app-icon.png -Force
```

Then rebuild a native binary so iOS/Android icons regenerate (`eas build` / `npx expo prebuild`).

## Store / Expo icon sizes

| File | Size | Notes |
|------|------|-------|
| `app-icon.png` | **1024×1024** | Opaque store / Expo icon (from web `icon-192.png`) |
| `../images/android-icon-foreground.png` | **1024×1024** | Transparent adaptive foreground; mark padded into safe zone |

Regenerate with sharp from web masters if branding assets change (do not invent artwork).
