# AtGlance site

Marketing site, documentation and account area for AtGlance.

- **Backend**: Laravel 12 (PHP 8.2+). It is the JSON API for the SPA and hosts the admin CMS.
- **Admin CMS**: Filament 4 at `/admin`. Only users with role `admin` can use it.
- **Frontend**: React 19 SPA in `frontend/` (CRA + craco, Tailwind, shadcn/ui).
- **Database**: MySQL 8 (MariaDB 10.4+ also works). Tests use SQLite in memory.
- **Auth**: Laravel Sanctum SPA auth (session cookie + CSRF), plus GitHub SSO through Laravel Socialite.

## Layout

```
app/
  Http/Controllers/Api/   AuthController, GithubAuthController, CmsController
  Http/Resources/         UserResource (JSON shape the SPA expects)
  Models/                 User, PricingPlan, Faq, Doc, Page, Setting
  Filament/               Admin panel resources and settings pages
config/atglance.php       Admin account and frontend URL
database/
  migrations/             Schema
  seeders/                AdminUserSeeder, ContentSeeder (+ content/*.json default copy)
routes/api.php            All /api routes
tests/Feature/            API, auth, GitHub SSO and admin panel tests
frontend/                 React SPA
docker/                   Laravel image and boot scripts
docker-compose.yml        mysql + app (Laravel) + frontend (nginx), exposed on :8080
```

## API

| Method | Path | Auth |
|---|---|---|
| GET | `/api` | public (health) |
| POST | `/api/auth/register`, `/api/auth/login`, `/api/auth/logout` | public (CSRF) |
| GET | `/api/auth/me` | logged in |
| GET | `/api/auth/github/start` | public; returns `{auth_url}` |
| POST | `/api/auth/github/callback?code=&state=` | public; state is verified against the session |
| GET | `/api/cms/pricing`, `/api/cms/faqs`, `/api/cms/docs`, `/api/cms/docs/{slug}`, `/api/cms/contact`, `/api/cms/pages/{slug}` | public |
| GET | `/api/downloads` | logged in |
| GET / POST | `/api/licenses` | logged in; list (with plan + limit) / request a licence. The request is saved as under review, the key is returned, and a 5-digit verification code is emailed. |
| POST | `/api/licenses/{id}/confirm` | logged in; `{code}`. Confirms the request and verifies the email |
| POST | `/api/licenses/{id}/confirm-code` | logged in; resends the confirmation code |
| GET | `/api/licenses/{id}/key` | logged in; shows the key again, for 30 minutes after creation |
| POST | `/api/licenses/{id}/revoke-code` | logged in; emails a 5-digit code to confirm revoking |
| DELETE | `/api/licenses/{id}` | logged in; revoke. Body: `{password}` or `{code}` |
| POST | `/api/licenses/verify` | licence key; verifies the key **and marks the licence In Use** for this console |
| POST | `/api/licenses/activate` | licence key; same as `verify` (alias) |
| POST | `/api/licenses/heartbeat` | licence key; console reports it is alive (records last seen) |
| GET | `/api/auth/providers` | public; `{github: bool}` |

### Management Console licences

Users create licences on the SPA dashboard. The key is shown right away and can be viewed again for **30 minutes** after creation. A 5-digit code is emailed; the licence works once the user enters that code (status `unverified`, then `ready`). If a user cannot receive the code (e.g. a test mailbox), an admin can **Approve** the licence under **Admin → Licences** to skip the code step. **Admin approval is otherwise optional.** It applies only to users flagged with **"Licences need admin approval"** on their admin edit page, e.g. test accounts. Their verified licences stay `under_review` until an admin approves or declines them under **Admin → Licences**, and the user is emailed either way. Each licence works on **exactly one** Management Console instance. On the Free plan, that means one licence and one console.

The Management Console calls three endpoints, each with `Authorization: Bearer <licence key>` and `Accept: application/json`. `activate` and `heartbeat` also take a JSON body `{"instance_id": "...", "hostname": "...", "version": "..."}`.

- `instance_id` is recommended (if omitted, the console is identified by `hostname`, else by its IP address): 8–100 characters from `A-Z a-z 0-9 . _ : -`. The console must generate it once, store it permanently (for example a UUID in its database), and send the same value on every call.
- `hostname` and `version` are optional. They are shown on the user's dashboard.

| Call | When the console makes it | Results |
|---|---|---|
| `POST /api/licenses/verify` | Optional pre-check during install | `200` with `status` `available` or `in_use` (plus `console` details); `403` with `status` `unverified` (code not entered) or `under_review` (approval required); `401` for a bad key |
| `POST /api/licenses/activate` | Once, when the licence key is entered during install | `201` on first activation (the licence becomes **In Use**); `200` when the same `instance_id` activates again, for example after a reinstall; `403` with `status` `unverified` or `under_review` when the licence is not usable yet; `409` with `status: in_use_elsewhere` when another console owns the licence; `401` for a bad or revoked key |
| `POST /api/licenses/heartbeat` | Periodically, for example hourly, and on startup | `200` records **last seen**; `409` with `status` `not_activated` or `in_use_elsewhere`; `401` once the licence is revoked. On `401` or `409` the console should stop and ask for a new licence. |

```bash
curl -X POST https://<site>/api/licenses/activate   -H "Accept: application/json" -H "Content-Type: application/json"   -H "Authorization: Bearer <licence key>"   -d '{"instance_id":"8f1c2d9e-...","hostname":"ops-01","version":"1.2.0"}'
```

A successful response looks like `{"valid": true, "status": "in_use", "user": {id, email, name}, "license": {name}, "plan": "free", "console": {instance_id, hostname, version, activated_at, last_seen_at}}`.

- The dashboard shows each licence as **Awaiting code**, **Under review** (flagged users only), **Ready** or **In Use**, with the console hostname and version, and "Last seen" from the latest heartbeat.
- A licence can't be moved to another console. Revoke it (password or emailed code), then create a new one. Revoking also deletes the activation, so the old console gets `401` on its next heartbeat.
- Licences never expire. Keys are Sanctum personal access tokens with the `console:license` ability. They are stored hashed and shown to users as `atg_...` (without Sanctum's `<id>|` prefix; both forms authenticate). Activations are stored in the `license_activations` table.
- **Plan limits:** each user has a `plan` (default `free`). The limits are set in `config/atglance.php` (`plans`): Free allows 1 licence and Enterprise is unlimited. Admins change a user's plan in Filament, under Users. When a user is at the limit, creating another licence returns `403`.

## How SPA auth works

1. The SPA calls `GET /sanctum/csrf-cookie`. This sets the `XSRF-TOKEN` cookie.
2. Axios sends the value back in the `X-XSRF-TOKEN` header (`withXSRFToken: true` in `frontend/src/lib/api.js`).
3. Login and register start a normal Laravel session. The same session also logs the admin into `/admin`.

The SPA and Laravel must share one origin, or the SPA host must be in `SANCTUM_STATEFUL_DOMAINS` and `CORS_ORIGINS`. Both the dev proxy and the nginx config serve them from one origin.

## Local development (no Docker)

You need PHP 8.2+ with `intl`, `pdo_mysql` and `pdo_sqlite`, Composer 2, Node 20+ with Yarn, and MySQL. XAMPP works.

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
# Edit .env: DB_* settings and ADMIN_PASSWORD
php artisan migrate --seed
php artisan serve                  # http://127.0.0.1:8000

# Frontend (second terminal)
cd frontend
yarn install
yarn start                         # http://localhost:3000
```

In dev, `craco.config.js` proxies `/api`, `/sanctum`, `/admin`, `/livewire` and the Filament assets to `http://127.0.0.1:8000`. Everything therefore runs on `localhost:3000`. Keep `REACT_APP_BACKEND_URL` empty. To point the proxy at another server, set `LARAVEL_DEV_URL`.

For quick local work you can use SQLite instead of MySQL. Set `DB_CONNECTION=sqlite` and remove the other `DB_*` lines.

- Admin panel: http://localhost:3000/admin. Log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- Tests: `php artisan test`.

## Docker

```bash
cp .env.example .env
# Set at least: APP_KEY (php artisan key:generate --show), DB_PASSWORD, ADMIN_PASSWORD
# Set FRONTEND_URL=http://localhost:8080
docker compose up -d --build
```

- Site: http://localhost:8080
- Admin: http://localhost:8080/admin

The frontend container (nginx) serves the SPA. It proxies `/api`, `/sanctum`, `/admin`, `/livewire` and `/{css,js,fonts}/filament` to the `app` container.

On boot the `app` container:
- runs migrations and caches config, routes and views (serversideup `AUTORUN_ENABLED`);
- runs `db:seed` (`docker/entrypoint.d/60-atglance-seed.sh`).

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel encryption key. Required. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Admin account. The seeder creates it and re-syncs the password on every deploy. |
| `FRONTEND_URL` | SPA origin. Allowed for CORS; `/` redirects here. |
| `CORS_ORIGINS` | Extra allowed origins, comma separated. Wildcards are never used. |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts (with port) that get cookie auth. Must include the SPA host. |
| `SESSION_SECURE_COOKIE` | Set `true` in production (HTTPS). |
| `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | GitHub OAuth app (optional) |
| `GITHUB_REDIRECT_URI` | `<FRONTEND_URL>/auth/sso/github/callback` |
| `REACT_APP_BACKEND_URL` | Frontend build arg. Leave empty for the same-origin setup. |

## Adding a feature

- **New content type**: create a migration and model, then run `php artisan make:filament-resource Name --generate`. Add a read endpoint in `CmsController` and `routes/api.php` if the SPA needs it.
- **New setting group**: subclass `App\Filament\Pages\SettingsPage`, and add a key constant to `App\Models\Setting`.
- **Protected API**: add the `auth:sanctum` middleware to the route. For admin-only routes, also check `$request->user()->isAdmin()`.

## GitHub OAuth app

In the GitHub OAuth app settings, set:
- Homepage URL: `FRONTEND_URL`
- Authorization callback URL: `<FRONTEND_URL>/auth/sso/github/callback`

The SPA page at that URL posts `code` and `state` to `/api/auth/github/callback`.
