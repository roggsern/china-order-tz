# CHINA ORDER TZ — Mobile (`apps/mobile`)

Expo (React Native) foundation for the customer app. Talks **directly** to Laravel `/api/v1` (Contract v1). Does **not** use the Next.js BFF under `apps/web`.

## Stack (locked)

- Expo SDK 57 + TypeScript + Expo Router
- TanStack Query (server state)
- Zustand (auth + journey client state)
- zod (response parsing)
- expo-secure-store (auth token only)
- expo-web-browser / expo-linking (payment return flows later)
- expo-image, FlashList (catalog UI later)

## Local setup

```bash
cd apps/mobile
cp .env.example .env
npm install
npm run start
```

Then open Expo Go or an emulator (`npm run android` / `npm run ios`).

### API base URL

Set once via env (preferred) or `app.json` → `expo.extra.apiBaseUrl`.

| Source | Variable / key |
|--------|----------------|
| Env | `EXPO_PUBLIC_API_BASE_URL` |
| app.json | `extra.apiBaseUrl` |

Production default (documented):

```text
https://api.chinaordertz.com/api/v1
```

Local examples (Android emulator → host machine):

```text
EXPO_PUBLIC_API_BASE_URL=http://10.0.2.2:8000/api/v1
```

All feature code must read the URL from `src/core/config` — never hardcode hosts.

## Architecture

```text
app/                 Expo Router (thin screens)
src/core/            api, auth, config, errors, storage
src/features/        feature modules (placeholders in M3.1)
src/shared/          components, hooks, types, utils
```

Flow:

```text
UI (routes) → features → core/api|auth → Laravel /api/v1
```

Commerce journeys stay separated as backend codes:

- `CHINA_IMPORT` — order from China
- `TZ_LOCAL` — buy from TZ

Do not rename these values in mobile.

## State ownership

| Concern | Owner |
|---------|--------|
| Server/cache data | TanStack Query |
| Auth session flags + user | Zustand `useAuthStore` (in-memory) |
| Active journey | Zustand `useJourneyStore` |
| Access token | **expo-secure-store only** |

## Auth token policy

- Persist with `secureTokenStorage` (`expo-secure-store`).
- Never put the token in AsyncStorage or Zustand `persist`.
- Zustand holds only: `status`, `user`, `bootstrapStatus`.
- Startup splash: read token → if present, `GET /me` → authenticated or clear + login.
- Login `POST /login` / Register `POST /register`: save top-level `token`, set user, enter app.
- Logout `POST /logout`: clear SecureStore even if the API call fails.
- **No refresh-token flow** (Sanctum personal access tokens; backend TTL applies).

## Scripts

| Script | Purpose |
|--------|---------|
| `npm run start` | Expo dev server |
| `npm run typecheck` | `tsc --noEmit` |
| `npm run lint` | ESLint |
| `npm run test` | Jest |

## Monorepo note

`apps/mobile` is intentionally **not** an npm workspace member yet (Expo + root workspaces can fight over React Native resolution). Install and run from `apps/mobile` directly. Root may expose convenience scripts that `cd` into this package.
