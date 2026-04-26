# AtGlance Marketing + Docs Site — PRD

## Original problem statement
Senior product designer + full-stack architect deliverable: design and ship the AtGlance marketing + documentation website (CLI + Management Console). Audience: SREs, platform teams, DevOps engineers, infra-heavy enterprises. Theme: dark + gold/amber matching the existing architecture diagrams. References: traefik.io, goteleport.com.

## User decisions (locked)
- Full-stack: marketing + docs + admin CMS (no separate planning .md spec)
- Theme: dark + gold/amber (matches diagrams)
- All MVP pages: Home, Product, CLI, Console, Architecture, Use Cases, Security, Pricing, FAQ, About, Contact, Terms, Privacy, Docs (landing + 5 pages)
- No demo lead capture; free signup with single Free pricing tier
- Auth: email/password JWT + GitHub OAuth (creds provided by user)
- Downloads (CLI + Self-hosted Console) gated to logged-in users
- Admin CMS to edit: pricing, docs, FAQ, contact, static pages (Terms/Privacy/About), download URLs

## Architecture
- **Backend**: FastAPI + MongoDB (motor). Single `/app/backend/server.py`. JWT (httpOnly cookies) + bcrypt. GitHub OAuth via httpx. Startup-time seeding of admin + content.
- **Frontend**: React 19 + Tailwind + Shadcn. Geist + JetBrains Mono fonts. Custom Markdown renderer (no extra deps). React Router v7.
- **Theme tokens** in `/app/frontend/src/index.css` (amber #F59E0B over #0a0a0a base).

## Personas
- Platform Engineer — config rollout safety, host inventory
- SRE Manager — audit trail, RBAC, incident postmortems
- Security/Compliance Lead — self-hosted, no egress, role separation

## What's been implemented (Feb 2026)
### Backend (`/api/...`)
- Auth: register, login, logout, me, refresh, github/start, github/callback
- Public CMS reads: pricing, faqs, docs (list + slug), contact, pages/{slug}
- Auth-gated: GET /downloads
- Admin writes (RBAC enforced): pricing, faqs, docs (DocsBulk), contact, pages/{slug}, downloads, admin/docs/full
- Admin seeded from .env (admin@atglance.io / Admin@12345)
- Content seeded: 2 pricing plans, 6 FAQs, 5 docs, contact info, 3 static pages (about/terms/privacy), download config

### Frontend (`/...`)
- Marketing: Home (animated CLI typewriter hero), Product, CLI, Console, Architecture (3 diagrams), Use Cases, Security (RBAC table), Pricing (from API), FAQ (accordion from API), Contact, About/Terms/Privacy (markdown from CMS)
- Docs: landing + 5 markdown pages with sidebar nav
- Auth: Login, Register, GitHub SSO button, /auth/sso/github/callback
- User dashboard: CLI + Console download cards from /api/downloads
- Admin CMS at /admin/{pricing,docs,faqs,contact,pages,downloads}

## Test coverage (iteration_1)
- 37/37 backend pytest tests pass
- All 12 marketing pages, docs, auth, dashboard, admin CMS verified end-to-end

## Backlog / future iterations
- P1: Make marketing copy editable from CMS (Home hero, feature cards)
- P1: Quickstart "5-minute setup" interactive flow with copy buttons
- P2: Searchable docs (Algolia/lunr-style client-side index)
- P2: Production-grade GitHub OAuth — strict state enforcement once frontend persists state across redirect
- P2: Content versioning + draft/publish in admin CMS
- P2: Public status badge / changelog page
- P2: Newsletter / RSS for changelog
- P3: i18n (multi-language docs)

## Files of interest
- Backend: `/app/backend/server.py`, `/app/backend/.env`
- Frontend root: `/app/frontend/src/App.js`, `/app/frontend/src/index.css`
- Auth: `/app/frontend/src/lib/auth-context.jsx`, `/app/frontend/src/lib/api.js`
- Admin CMS: `/app/frontend/src/pages/Admin.jsx`
- Test creds: `/app/memory/test_credentials.md`
