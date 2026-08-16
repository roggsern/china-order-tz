# Google services file strategy (MOBILE-RELEASE-01)

## Current state

- `app.json` / `app.config.js` reference `./google-services.json` for Android FCM.
- `app.config.js` allows EAS to override via `GOOGLE_SERVICES_JSON` (or `GOOGLE_SERVICES_FILE`).
- The production client file may still be tracked in Git for local/EAS continuity.

## Safe production direction (owner action)

1. Create an EAS file environment variable (do not paste contents into chat logs):

```bash
cd apps/mobile
eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment production
```

2. Confirm a production build still resolves FCM (Expo/EAS injects the file path into the build env).

3. Only then untrack the repo copy:

```bash
git rm --cached apps/mobile/google-services.json
```

4. Add `google-services.json` to `apps/mobile/.gitignore` (keep `google-services.json.example`).

Do **not** delete the local file until step 1–2 succeed. Do **not** rotate Firebase keys automatically from this repo task.
