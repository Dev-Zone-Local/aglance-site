# AtGlance site

Marketing site, documentation and account area for AtGlance. Everything is one Laravel app.

- **Laravel 12** (PHP 8.2+): public pages, docs, sign-in, the user dashboard, the JSON API for the Management Console, and the admin CMS.
- **Pages**: Blade views + Tailwind CSS. Interactive dashboard pages (Licences, Profile) are **Livewire** components; small UI bits (menus, copy buttons) use Alpine.js, which ships with Livewire.
- **Admin CMS**: Filament 4 at `/admin`. Only users with role `admin` can use it.
- **Database**: MySQL 8 (MariaDB 10.4+ also works). Tests use SQLite in memory.
- **Auth**: Laravel session login, password reset, email verification, and GitHub SSO through Laravel Socialite.

## Layout

```
app/
  Http/Controllers/         SiteController (public pages), DashboardController, SitemapController
  Http/Controllers/Auth/    Login, Register, PasswordReset, Github (web sign-in)
  Http/Controllers/Api/     JSON API (console licence API, CMS, account)
  Livewire/                 Licences, Profile (dashboard pages)
  Support/                  LicenceManager, InstallCatalog, Downloads, Sitemap, Markdown, ...
  Models/                   User, License, PricingPlan, Faq, Doc, Page, Setting
  Filament/                 Admin panel resources and settings pages
resources/
  views/site/               Public pages (home, product, cli, console, pricing, docs, ...)
  views/auth/               Sign in, sign up, forgot / reset password
  views/dashboard/          Overview and install wizard
  views/livewire/           Licences and Profile
  views/components/         Layouts and shared Blade components (x-button, x-card, x-hero, x-glyph icons, ...)
  css/app.css, js/app.js    Tailwind entry and Alpine components (built by Vite into public/build)
config/atglance.php         Admin account, site URL, plans
routes/web.php              Website, sign-in and dashboard routes
routes/api.php              /api routes
tests/Feature/              Pages, auth, dashboard, licences, API and admin panel tests
deploy/                     aaPanel deploy scripts and the static download pages for app.atglance.live
docker/                     Laravel image and boot scripts
docker-compose.yml          mysql + app (Laravel), exposed on :8080
```

## JSON API

The website itself does not use these endpoints (it uses the Blade pages and Livewire). The Management Console uses the licence endpoints marked "licence key"; the others are kept for scripts and integrations.

| Method | Path | Auth |
|---|---|---|
| GET | `/api` | public (health) |
| POST | `/api/auth/register`, `/api/auth/login`, `/api/auth/logout` | public (CSRF) |
| GET | `/api/auth/me` | logged in |
| POST | `/api/auth/forgot-password`, `/api/auth/reset-password` | public; the reset email links to the page `/reset-password?token=&email=` (valid 60 min, single use) |
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
| POST | `/api/licenses/verify` | licence key; read-only check. With `org_name` in the body it also marks the licence **In Use** for that organization |
| POST | `/api/licenses/activate` | licence key; `{org_name}` required. Marks the licence In Use |
| POST | `/api/licenses/heartbeat` | licence key; console reports it is alive (records last seen) |
| PUT | `/api/licenses/org` | licence key; `{org_name}` required. Changes the organization of an activated licence (owning console only) |
| GET | `/api/auth/providers` | public; `{github: bool}` |

### Management Console licences

Users create licences on the dashboard (`/dashboard/licences`). The key is shown right away and can be viewed again for **30 minutes** after creation. A 5-digit code is emailed; the licence works once the user enters that code (status `unverified`, then `ready`). If a user cannot receive the code (e.g. a test mailbox), an admin can **Approve** the licence under **Admin → Licences** to skip the code step. **Admin approval is otherwise optional.** It applies only to users flagged with **"Licences need admin approval"** on their admin edit page, e.g. test accounts. Their verified licences stay `under_review` until an admin approves or declines them under **Admin → Licences**, and the user is emailed either way. Each licence works on **exactly one** Management Console instance. On the Free plan, that means one licence and one console.

The Management Console calls three endpoints, each with `Authorization: Bearer <licence key>` and `Accept: application/json`. `activate` and `heartbeat` also take a JSON body `{"org_name": "...", "instance_id": "...", "hostname": "...", "version": "..."}`. `org_name` is required to activate: the licence is marked In Use only once the console reports its organization, and users see that organization name on the dashboard.

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

- The dashboard shows each licence as **Pending**, **Under review** (flagged users only), **Ready** or **In Use**, with the console hostname and version, and "Last seen" from the latest heartbeat.
- A licence can't be moved to another console. Revoke it (password or emailed code), then create a new one. Revoking also deletes the activation, so the old console gets `401` on its next heartbeat.
- Licences expire **one year** after creation (`expires_at`); expired keys get `403` with `status: expired`. Licences created before expiry was added have no `expires_at` and never expire. Keys are Sanctum personal access tokens with the `console:license` ability. They are stored hashed and shown to users as `atg_...` (without Sanctum's `<id>|` prefix; both forms authenticate). Activations are stored in the `license_activations` table.
- **Plan limits:** each user has a `plan` (default `free`). The limits are set in `config/atglance.php` (`plans`): Free allows 1 licence and Enterprise is unlimited. Admins change a user's plan in Filament, under Users. When a user is at the limit, creating another licence returns `403`.

## Admin content

- **Downloads** (Settings → Downloads): publish CLI / Console releases. Each release has a version, an optional title, a type (Major, Feature, Bug fix, Security, Beta), a release date, a short summary and Markdown release notes. The last 20 releases per product are kept and shown on the public **/releases** page (filter with `?product=cli` or `?product=console`). Product pages and the dashboard link to the latest release notes, and the optional release email includes the summary and a link to them.
- **Product pages** (Content → Product pages, `/admin/product-pages`): edit the public `/console` and `/cli` pages. Each product has tabs for the **Hero** (badge, title, subtitle, buttons, check marks, hero screenshot), **Feature sections** (icon, heading, text, bullets, screenshot and caption; drag to reorder, duplicate or hide), **More features** (cards without screenshots), **Gallery** (extra screenshots) and **Page blocks** (show or hide the built-in architecture, install and other blocks). Uploads go to `storage/app/public/showcase`; replaced or removed images are deleted on save. **Reset to defaults** restores the built-in text from `App\Support\ProductShowcase`. Sections without a screenshot show an empty window frame; signed-in admins also see what to capture.
- **Contact** (Settings → Contact): page heading and intro, response time, general / sales / support / security / billing / press emails, phone, support hours, company name, address and map link, and social links (GitHub, X, LinkedIn, YouTube, Discord, Slack, status page). Empty fields are hidden. Toggles control whether the address is shown and whether social icons appear in the site footer.

## Local development (no Docker)

You need PHP 8.2+ with `intl`, `pdo_mysql` and `pdo_sqlite`, Composer 2, Node 20+ with Yarn, and MySQL. XAMPP works.

```bash
composer install
yarn install
cp .env.example .env
php artisan key:generate
# Edit .env: DB_* settings, ADMIN_PASSWORD, APP_URL / FRONTEND_URL (e.g. http://localhost:8000)
php artisan migrate --seed
yarn build                         # or `yarn dev` while editing views/CSS (hot reload)
php artisan serve                  # http://127.0.0.1:8000
```

Node is only needed to compile CSS/JS (Vite + Tailwind into `public/build`); nothing JavaScript runs on the server.

For quick local work you can use SQLite instead of MySQL. Set `DB_CONNECTION=sqlite` and remove the other `DB_*` lines.

- Admin panel: http://127.0.0.1:8000/admin. Log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- Local test user: set `TEST_USER_EMAIL` / `TEST_USER_PASSWORD` in `.env` (with `APP_ENV=local`) and run `php artisan db:seed --class=TestUserSeeder`.
- Tests: `php artisan test`.

## Docker

```bash
cp .env.example .env
# Set at least: APP_KEY (php artisan key:generate --show), DB_PASSWORD, ADMIN_PASSWORD
# Set APP_URL / FRONTEND_URL=http://localhost:8080
docker compose up -d --build
```

- Site: http://localhost:8080
- Admin: http://localhost:8080/admin

The image builds the CSS/JS in a Node stage, then serves everything from Laravel (php-fpm + nginx). On boot the `app` container:
- runs migrations and caches config, routes and views (serversideup `AUTORUN_ENABLED`);
- runs `db:seed` (`docker/entrypoint.d/60-atglance-seed.sh`).

## Deploying on aaPanel

`deploy/atglance.live.sh` and `deploy/dev.atglance.live.sh` pull the branch, run composer, build CSS/JS (`yarn build`), migrate, cache, and make sure nginx sends every path to `public/index.php` (the aaPanel URL-rewrite file gets `try_files $uri $uri/ /index.php?$query_string;`). They finish with a health check.

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel encryption key. Required. |
| `APP_URL` | Site address. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Admin account. The seeder creates it and re-syncs the password on every deploy. |
| `FRONTEND_URL` | Public site address (defaults to `APP_URL`). Used in emails, the sitemap and the GitHub callback. |
| `CORS_ORIGINS` | Extra allowed origins for the API, comma separated. Wildcards are never used. |
| `SESSION_SECURE_COOKIE` | Set `true` in production (HTTPS). |
| `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | GitHub OAuth app (optional; can also be set in the admin panel) |
| `GITHUB_REDIRECT_URI` | `<FRONTEND_URL>/auth/sso/github/callback` |

## Adding a feature

- **New public page**: add a Blade view in `resources/views/site`, a method in `SiteController`, a route in `routes/web.php`, and (if it should be indexed) a line in `App\Support\Sitemap`.
- **New dashboard page**: a controller + view, or a Livewire component in `app/Livewire` for interactive forms. Put it under the `auth` route group.
- **New content type**: create a migration and model, then run `php artisan make:filament-resource Name --generate`, and read it in the page controller.
- **New setting group**: subclass `App\Filament\Pages\SettingsPage`, and add a key constant to `App\Models\Setting`.

## GitHub OAuth app

In the GitHub OAuth app settings, set:
- Homepage URL: `FRONTEND_URL`
- Authorization callback URL: `<FRONTEND_URL>/auth/sso/github/callback`

That URL is handled by `Auth\GithubController::callback`, which signs the user in and sends admins to `/admin`.
