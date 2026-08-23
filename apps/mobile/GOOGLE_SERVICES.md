# Google services file strategy

## Current state

- `app.json` / `app.config.js` reference `./google-services.json` for Android FCM.
- `app.config.js` allows EAS to override via `GOOGLE_SERVICES_JSON` (or `GOOGLE_SERVICES_FILE`).
- The production **client** file is still tracked in Git for local/EAS continuity.
- Package in that file: `com.chinaordertz.mobile`.
- This is Firebase **Android client** config (not a service-account JSON).
- `google-services.json` is listed in `.gitignore` so it stays local after untrack.
- `google-services.json.example` remains the committed placeholder.

## Safe production direction (owner action)

Do **not** create a new Firebase project. Do **not** rotate keys from this task.

1. Create EAS file environment variables (do not paste file contents into chat):

```bash
cd apps/mobile
eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment production
eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment preview
eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment development
```

2. Confirm a production/preview build still resolves FCM (EAS injects the file path).

3. Only then untrack the repo copy:

```bash
git rm --cached apps/mobile/google-services.json
```

Do **not** delete the local file until steps 1–2 succeed.
