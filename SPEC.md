# Cut Tracker — Implementation Spec (Laravel + PWA)

A personal web app for logging daily metrics during a fitness cut. Single-user, installable as a PWA on phone and desktop, works offline, syncs to a self-hosted Laravel backend with persistent SQLite.

## Goals

- Log daily nutrition, training, sleep, and bodyweight in under 60 seconds
- Installable on phone (Add to Home Screen) — looks and feels like a native app
- Works offline: open the app, log a day, close it; sync happens when back online
- Same dataset visible across phone, laptop, tablet
- Export full dataset as JSON for periodic review
- Self-hosted on Kubernetes with persistent SQLite volume

## Non-goals

- Multi-user (single user only — no registration, password reset by SSH if needed)
- Native iOS/Android apps (PWA covers it)
- Food database integration (user enters totals from their existing tracker)
- In-app charts (export to LLM for analysis)

## Stack

### Backend
- **PHP 8.3, Laravel 11**
- **SQLite** via Eloquent (single-file database, persistent volume)
- **Laravel Sanctum** for API token auth
- **Vite** for frontend asset bundling (ships with Laravel)

### Frontend (PWA)
- **Vanilla JS** — no Vue/React/Alpine. The UI is simple, keep it that way.
- **Service worker** for offline caching and background sync
- **IndexedDB** for offline write queue
- **Web App Manifest** for installability
- **Workbox** library to manage the service worker (CDN-loaded, no npm gymnastics)

## Data model

Two tables managed via Laravel migrations.

### `days`

| column | type | notes |
|---|---|---|
| `id` | bigIncrements | |
| `date` | date, unique | one row per calendar date |
| `weight_kg` | decimal(5,2) nullable | morning bodyweight |
| `kcal` | integer nullable | |
| `protein_g` | integer nullable | |
| `carbs_g` | integer nullable | |
| `fat_g` | integer nullable | |
| `steps` | integer nullable | |
| `sleep_hours` | decimal(3,1) nullable | |
| `hunger` | tinyInteger nullable | 1–5 |
| `energy` | tinyInteger nullable | 1–5 |
| `refeed` | boolean default false | |
| `session` | enum nullable | `Push`, `Pull`, `Legs`, `Other` |
| `rpe` | decimal(3,1) nullable | 1–10 |
| `lifts` | text nullable | free-form |
| `notes` | text nullable | |
| `waist_cm` | decimal(5,1) nullable | |
| `photos_taken` | boolean default false | |
| `timestamps` | | |

Upsert semantics on save (insert-or-update by date).

### `settings`

Generic key-value store. Columns: `key string PK`, `value text nullable`, `timestamps`. Used for `start_date`, `kcal_target`, `protein_target`.

## API

All endpoints return JSON. Protected endpoints require `Authorization: Bearer <token>` from Sanctum.

| method | path | body | response |
|---|---|---|---|
| `POST` | `/api/login` | `{password}` | `{token}` on success, `401` otherwise |
| `POST` | `/api/logout` | — | `204`, revokes current token |
| `GET` | `/api/me` | — | `{authenticated: true}` or `401` |
| `GET` | `/api/days` | — | `[{day}, …]` sorted ascending |
| `GET` | `/api/days/{date}` | — | `{day}` or `404` |
| `PUT` | `/api/days/{date}` | `{day}` | upserted row |
| `DELETE` | `/api/days/{date}` | — | `204` |
| `GET` | `/api/settings` | — | `{start_date, kcal_target, protein_target}` |
| `PUT` | `/api/settings` | partial object | merged result |
| `GET` | `/api/export` | — | `{exported_at, settings, days}` |
| `POST` | `/api/sync` | `{ops: [{type, date, data}, …]}` | per-op results |

The `/api/sync` endpoint is for the PWA's offline queue — it accepts a batch of operations (`put`, `delete`) and processes them in order, returning success/failure per item. This is how the service worker replays pending writes.

## Auth

Single password, no users table beyond a single seeded row. Implementation:

- `APP_PASSWORD_HASH` env var holds a `bcrypt` hash
- `POST /api/login` checks the password against the hash via `Hash::check()`
- On success, mint a Sanctum personal access token tied to the single seeded `User` row (created on first boot)
- Token sent back to client, stored in localStorage on the PWA
- Rate limit login: 5 attempts/minute per IP via Laravel's `RateLimiter`
- Token lifetime: 30 days, rolling via Sanctum's `expires_at` refresh

Provide an Artisan command `php artisan app:hash-password` that prompts for a password and prints the hash. Use this to generate the value for the env var.

## PWA specifics

### Manifest (`public/manifest.webmanifest`)
- `name`: "Cut Tracker"
- `short_name`: "Cut"
- `start_url`: `/`
- `display`: `standalone`
- `theme_color`: matches the app's dark accent
- `background_color`: matches the app background
- Icons: 192×192 and 512×512 PNGs (maskable variants too)

### Service worker (`public/sw.js`)

Use Workbox via CDN. Strategies:

- **App shell** (`/`, JS, CSS, manifest, icons): `StaleWhileRevalidate` — instant load, updated in background
- **`/api/days`, `/api/settings`** GETs: `NetworkFirst` with 3s timeout, fall back to cache — so the UI populates fast even on flaky mobile
- **PUT/DELETE/POST**: not cached. If the request fails (offline), client-side code catches it and enqueues to IndexedDB instead.

### Offline write queue

When a PUT/DELETE fails because the device is offline:

1. The op is serialized into an IndexedDB store called `pending_ops` with `{id, type, date, data, queued_at}`
2. UI shows a small badge: "N unsaved changes"
3. On `online` event or when the next successful request happens, the client batches everything in `pending_ops` and POSTs them to `/api/sync`
4. Successful ops are removed from IndexedDB; failed ops stay with an error flag so the user can see what didn't sync

Conflict resolution: last-write-wins by `date`. If two devices edit the same day offline, the one that syncs second wins. Acceptable for a single user.

### Install prompt

Detect `beforeinstallprompt` event, stash it, show a small "Install app" button in the corner that triggers it. Hide once installed.

## Frontend

Single-page, vanilla JS. Structure:

```
resources/views/app.blade.php       # the root HTML, loads the manifest and service worker
resources/js/app.js                  # bootstraps the SPA logic
resources/js/api.js                  # fetch wrappers, auth header, retry/queue logic
resources/js/db.js                   # IndexedDB wrappers for the offline queue
resources/js/ui.js                   # DOM manipulation, event handlers
resources/css/app.css                # styles (mostly carried over from prototype)
public/sw.js                         # service worker (Workbox)
public/manifest.webmanifest
public/icons/                        # PWA icons
```

The PHP/Blade side serves a single `app.blade.php` that contains the same field layout as the existing prototype. Behavior changes from standalone:

- Replace direct DOM access with `fetch` against `/api/*`
- Login screen renders when `GET /api/me` returns 401
- "Saving…" indicator, debounce save by 500ms so typing a kcal value doesn't fire a request per keystroke
- Previous/next day buttons in addition to date picker
- "N unsaved changes" badge driven by IndexedDB queue length

## Deployment

### Container image

Multi-stage Dockerfile:

1. **Composer stage:** `composer:2` → install PHP deps with `--no-dev --optimize-autoloader`
2. **Node stage:** `node:20-alpine` → `npm ci && npm run build` to compile Vite assets
3. **Runtime stage:** `php:8.3-fpm-alpine` with extensions (`pdo_sqlite`, `mbstring`, `bcmath`, `opcache`) plus `nginx` and `supervisord` to run both in one container. Run as non-root (`uid 1000`).

Why single-container nginx+php-fpm: keeps the deployment one pod, one mount. For a personal app the operational simplicity outweighs the "two containers per pod is more correct" argument.

Listens on port 8080. Healthcheck: `GET /api/health` (returns 200 always, no auth required).

### Kubernetes manifests

`k8s/` directory:

- `deployment.yaml` — single replica (SQLite single writer), 100m/128Mi requests, 500m/512Mi limits, mounts PVC at `/var/www/html/database/sqlite`
- `service.yaml` — ClusterIP, port 80 → 8080
- `pvc.yaml` — 1Gi `ReadWriteOnce`
- `secret.yaml.example` — template for `APP_KEY`, `APP_PASSWORD_HASH`, `SANCTUM_*`
- `ingress.yaml` — TLS via cert-manager, configurable host
- `kustomization.yaml`

The SQLite database file lives at `/var/www/html/database/sqlite/cut.sqlite`. WAL mode enabled in a startup script for better concurrent read performance.

### Environment variables

| var | required | purpose |
|---|---|---|
| `APP_KEY` | yes | Laravel's encryption key, `php artisan key:generate` to mint |
| `APP_URL` | yes | Public URL, e.g. `https://cut.example.com` |
| `APP_PASSWORD_HASH` | yes | bcrypt hash of the login password |
| `DB_DATABASE` | no | defaults to `/var/www/html/database/sqlite/cut.sqlite` |
| `SANCTUM_STATEFUL_DOMAINS` | yes | the public hostname |
| `SESSION_DOMAIN` | yes | the public hostname |
| `APP_ENV` | no | `production` |

## Project layout

Standard Laravel 11 layout, with these additions:

```
cut-tracker/
├── app/
│   ├── Console/Commands/
│   │   └── HashPassword.php
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── DayController.php
│   │   ├── SettingController.php
│   │   ├── SyncController.php
│   │   └── ExportController.php
│   ├── Models/
│   │   ├── Day.php
│   │   └── Setting.php
│   └── Providers/
├── database/
│   ├── migrations/
│   │   ├── *_create_days_table.php
│   │   ├── *_create_settings_table.php
│   │   └── *_create_personal_access_tokens_table.php  (Sanctum)
│   └── seeders/
│       └── DatabaseSeeder.php  (creates the single user row)
├── public/
│   ├── sw.js
│   ├── manifest.webmanifest
│   └── icons/
├── resources/
│   ├── views/app.blade.php
│   ├── js/
│   │   ├── app.js
│   │   ├── api.js
│   │   ├── db.js
│   │   └── ui.js
│   └── css/app.css
├── routes/
│   ├── api.php
│   └── web.php  (serves app.blade.php for everything non-API)
├── tests/
│   ├── Feature/
│   │   ├── AuthTest.php
│   │   ├── DaysTest.php
│   │   ├── SettingsTest.php
│   │   └── SyncTest.php
│   └── Unit/
├── k8s/
├── Dockerfile
├── docker/
│   ├── nginx.conf
│   ├── supervisord.conf
│   └── entrypoint.sh
├── vite.config.js
├── package.json
├── composer.json
└── README.md
```

## Testing requirements

PHPUnit, RefreshDatabase trait, in-memory SQLite. Cover:

- Unauthenticated requests get 401 on protected routes
- Login with correct/wrong password, rate limit kicks in after 5 attempts
- Upsert a day, retrieve, update, delete; partial updates don't wipe other fields
- Settings round-trip
- Sync endpoint applies a batch and returns per-op results
- Sync endpoint handles a mix of success and failure ops in one batch
- Export shape matches spec
- Date validation (reject `2025-13-99`)

Test suite under 10 seconds.

## Operational notes

- **Backups:** README documents `kubectl cp` of the SQLite file. Consider a CronJob later that copies the file to object storage daily.
- **Migrations:** standard Laravel, run via `php artisan migrate --force` in the entrypoint script on container start.
- **Logging:** Laravel's `stderr` channel for production so logs flow to `kubectl logs`. Don't log request bodies (bodyweight is mildly sensitive).
- **WAL mode:** entrypoint runs `PRAGMA journal_mode=WAL;` on the SQLite file on first start.
- **App icon:** generate a simple flat-color square with bold "C" or a barbell icon. Don't get fancy.

## What to give Claude Code

Drop this spec and the existing prototype `index.html` into a fresh directory. Tell Claude Code:

> Implement the spec end-to-end. Start with `composer create-project laravel/laravel .`, then build out the migrations, models, controllers, tests in that order. After backend tests are green, port the prototype UI into `resources/views/app.blade.php` + `resources/js/`, wire up the service worker and manifest, and verify the PWA installs locally with `npm run build && php artisan serve`. Finally build the Docker image and verify it runs end-to-end with a mounted volume.

Implementation order that minimizes rework:

1. Laravel install, base config, Sanctum
2. Migrations + models
3. Auth controller and tests
4. Day + Settings + Export controllers and tests
5. Sync controller and tests
6. Frontend port (HTML/CSS first, then JS, then API integration)
7. Service worker + manifest + offline queue
8. Dockerfile + nginx/supervisord config
9. Local end-to-end test
10. K8s manifests
11. README with setup, password generation, backup procedure

## Acceptance checklist

- [ ] `docker run` with required env vars boots the app, migrations run automatically
- [ ] Login screen appears on first visit, lets you in with the right password
- [ ] Phone Safari/Chrome shows "Add to Home Screen" prompt; installed app launches in standalone mode
- [ ] Airplane mode test: open app, log a day, see "1 unsaved change" badge; turn airplane mode off, badge clears, data appears on another device
- [ ] Logging a day on phone and refreshing on laptop shows the same data
- [ ] Stopping and restarting the container preserves data via the PVC mount
- [ ] Export JSON shape matches the prototype's format (so historical exports remain comparable)
- [ ] All PHPUnit tests pass
- [ ] Lighthouse PWA score ≥ 90
- [ ] `kubectl apply -k k8s/` deploys cleanly with cert-manager + nginx ingress
