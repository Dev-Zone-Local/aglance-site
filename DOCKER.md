# Running AtGlance in Docker

A single `docker compose up -d --build` brings the whole stack online —
MongoDB, the FastAPI backend, and the React frontend (served by nginx,
proxying `/api` to the backend).

## 1. Prerequisites

- Docker Engine ≥ 24
- Docker Compose v2 (built into recent Docker installs)

## 2. First-time setup

```bash
cp .env.docker.example .env
# edit .env and at least set JWT_SECRET + ADMIN_PASSWORD
docker compose up -d --build
```

Open <http://localhost:8080>.

Default admin (from `.env`):

- Email: `admin@atglance.io`
- Password: `Admin@12345` (override with `ADMIN_PASSWORD`)

## 3. Services

| Service    | Image               | Internal port | Exposed on host       |
| ---------- | ------------------- | ------------- | --------------------- |
| `mongo`    | `mongo:7`           | 27017         | not exposed           |
| `backend`  | built from `backend/` | 8001        | not exposed           |
| `frontend` | built from `frontend/` (nginx) | 80 | `${FRONTEND_PORT:-8080}` |

The frontend container terminates the public traffic. nginx serves the
React build and proxies `^/api/.*` to `backend:8001`. There is no need to
expose the backend or MongoDB to the host.

## 4. Configuration reference (`.env`)

| Variable               | Required | Default                  | Notes                                           |
| ---------------------- | -------- | ------------------------ | ----------------------------------------------- |
| `JWT_SECRET`           | yes      | —                        | Use `openssl rand -hex 32`                      |
| `ADMIN_EMAIL`          | no       | `admin@atglance.io`      | Email auto-promoted to `admin` role on signup   |
| `ADMIN_PASSWORD`       | yes      | —                        | Used to seed admin on first start               |
| `DB_NAME`              | no       | `atglance_db`            | MongoDB database name                           |
| `FRONTEND_PORT`        | no       | `8080`                   | Host port for the public site                   |
| `FRONTEND_URL`         | no       | `http://localhost:8080`  | Used for CORS allow-list                        |
| `REACT_APP_BACKEND_URL`| no       | (empty)                  | Empty = same-origin via nginx; override only if you serve the API on a separate host |
| `CORS_ORIGINS`         | no       | (empty)                  | Comma-separated extra origins                   |
| `GITHUB_CLIENT_ID`     | no       | (empty)                  | Disables GitHub SSO if blank                    |
| `GITHUB_CLIENT_SECRET` | no       | (empty)                  |                                                 |
| `GITHUB_REDIRECT_URI`  | no       | (empty)                  | Must match your GitHub OAuth app callback URL   |

## 5. Common operations

```bash
# Tail logs
docker compose logs -f backend
docker compose logs -f frontend

# Restart only the backend after a code change
docker compose up -d --build backend

# Stop and remove everything (keeps the Mongo volume)
docker compose down

# Stop and remove EVERYTHING including the database volume (destructive)
docker compose down -v

# Open a shell inside the backend
docker compose exec backend bash

# Open a Mongo shell
docker compose exec mongo mongosh atglance_db
```

## 6. Production checklist

- [ ] Set a strong `JWT_SECRET` (32+ random bytes, never reuse the example value)
- [ ] Change `ADMIN_PASSWORD`
- [ ] Put the frontend behind HTTPS — use a reverse proxy (Traefik, Caddy,
      nginx, or a managed LB). Terminate TLS there, forward to
      `frontend:80`.
- [ ] After enabling HTTPS, flip cookie flags to `secure=True` and
      `samesite="none"` if you are doing cross-site auth (in
      `backend/server.py::set_auth_cookies`).
- [ ] Snapshot the `mongo_data` volume regularly.
- [ ] Update `GITHUB_REDIRECT_URI` to match your production domain
      (must exactly match what is registered in the GitHub OAuth app).

## 7. Single-host deploy (typical SRE scenario)

```bash
git clone https://your-git/atglance.git
cd atglance
cp .env.docker.example .env
$EDITOR .env
docker compose pull   # if you publish images
docker compose up -d --build
```

Wire your reverse proxy (Caddy example) to `127.0.0.1:8080`:

```
atglance.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

That is the entire deploy.
