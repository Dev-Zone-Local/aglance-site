# AtGlance site redesign: Feature Inventory and Content Merge Map

Status: draft for review (2026-11-12). Nothing is built yet.
Sources: the CLI repo (`AtGlance/atGlance`, version 2.2.0, 3.0.0 unreleased), the Console repo
(`atGlance-managementGUI`), the live https://atglance.live/ and https://app.atglance.live/, and this repo.

Stack decision (confirmed): keep the Laravel 12 app. Redesign the Blade marketing pages in place. The
account area, licences, dashboard and Filament admin stay as they are. Content (releases, roadmap, FAQ,
features, commands) moves to Markdown/JSON files.

---

## 1. CLI features (`atglance`)

A single static Go binary for Linux with systemd 230 or newer. No daemon, no dependencies, about 3 MB.
Packages: `.deb` (amd64, arm64, armhf), `.rpm` (x86_64, aarch64), `.tar.gz` (amd64, arm64, armhf).

### Health (no console, no sudo)
| Command | What it does |
|---|---|
| `atglance status [--all] [--type all\|system\|application] [-o table\|json]` | One line per running or failed service: state, uptime, ports, health. Application and system services in separate sections. Exit 3 if a service is Failed or Restarting. |
| `atglance service <name> [-o json]` | One service in detail: unit file, main PID, restarts, ports, 24-hour error and warning count. |
| `atglance watch [--interval 5s] [--all] [--type T]` | Live view, refreshes every 5 seconds. |
| `atglance export [--logs] [--type T]` | Every loaded service as JSON, for monitoring and scripts. |
| `atglance version`, `atglance help` | Version and help. `-v` prints one debug line per console request. |

Health states: Working, Starting, Restarting, Done, Stopped, Failed, Error, Warning.

### Console connection (sudo)
| Command | What it does |
|---|---|
| `login [--server URL]` | Saves the console URL and a personal API key (`atgla-`). Key read from a hidden prompt, stdin or `ATGLANCE_TOKEN`, never an argument. Warns on `http://`. |
| `whoami` | Console user, organisation, key name and expiry. |
| `check` (alias `doctor`) | ok / warn / fail for root, settings, console, token, clock, system, systemd, journal, queue, key expiry (personal and service account), schedule. |
| `check --scheduled` | The cron variant: checks stored keys and asks the console to send expiry notices (15, 7, 3, 2, 1, 0 days). |
| `logout` | Forgets the key. The system identity stays. |

### System registration (sudo)
`system register [--name] [--tags] [--workspace] [--ip] [--service-account] [--force-new]`,
`system status`, `system deregister [--pin] [--force --id]`, `system reactivate [--pin] [--force --id]`,
`system rotate-key [--pin]`, `system service-account set|remove`.

### Config backups (sudo)
| Command | What it does |
|---|---|
| `sync [--scheduled]` | Sends the offline queue, then backs up every service whose unit file changed. Unchanged files are skipped. |
| `config backup <svc>\|--all [--local] [--force]` | Upload unit files. Queued if the console is down. |
| `config list <svc>` | Versions the console holds. |
| `config pull <id>` | Download a version, change nothing. |
| `config diff <id>` | Unified diff against the live file. Exit 0 same, 3 different. |
| `config restore <svc> <id> [--yes] [--allow-ssh-restart]` | Diff, confirm, back up, write, reload, restart, wait until stable. Rolls back if the restart fails. Refuses SSH over SSH. |

### Schedule and security (sudo)
`schedule enable|disable|status` (writes `/etc/cron.d/atglance`, random minutes);
`security events [--type] [--since] [--until] [--user] [--all] [-o json]`, `security lockouts` (org admins).

### Agent (3.0.0, unreleased)
`agent install|uninstall [--purge]`, `agent start|stop|restart`, `agent status [-o json]`, `agent run`,
`agent logs [-n 50] [-f]`. Outbound WSS connection to the console, no open ports. Runs on Linux (systemd),
Windows Server (service `AtGlanceAgent`) and macOS (launchd). Follows the console's Agent Monitoring switch.

### Other facts the site uses
- 1.x flags (`--show-my-services`, `--app X`) still work and print the new name. 1.x (Python) reaches end of support soon.
- Exit codes: 0 success, 1 error, 2 usage, 3 degraded or diff found.
- Files: `/etc/atglance/config.json`, `/var/lib/atglance/*` (all 0600).
- Performance (Raspberry Pi armv7): `status` 0.14 s CPU, under 8 MB memory, against 3.45 s for 1.x.

### CLI screenshots available
71 SVG terminal renders of real output in `docs/images/` (19) and `docs/images/v2/` (52, numbered 01-52).
They cover install, version, help, status (plain, sudo, all, json), service, export, watch, login, whoami,
check, register, system status, config backup/list/pull/diff/restore, sync, schedule and legacy flags.
They are SVG text, so they stay sharp and small. WebP conversion does not apply to them.
Gaps: no render for `security events`, `agent *` or `config restore` rollback. These need a real capture.

---

## 2. Console features (Management Console, self-hosted)

Laravel 12 app plus Kong gateway (port 8002 for the CLI), MySQL, Redis, worker, scheduler, optional MCP
server. Installs with one command on Linux (`curl ... | sudo bash`) or Windows (`irm ... | iex`, Docker
Desktop), or on ECS, Kubernetes, Azure Container Apps and Cloud Run.

### Screens (from `composer/routes/web.php`)
| Area | Route | Feature |
|---|---|---|
| Setup | `/install` | First-run setup wizard, licence entry, migrations |
| Auth | `/login`, `/auth/sso/{provider}` | Email login and SSO |
| Dashboard | `/dashboard` | Fleet overview |
| Systems | `/systems-registered`, `.../{id}/edit`, `.../{id}/services` | Registered servers, their services |
| Live monitoring | `/live-service-monitoring` | Live service state (agent) |
| Config backups | `/configuration-backups`, service versions, file view, download | Every version of every unit file |
| AI review | `/configuration-backups/{id}/ai-validations/{id}` | AI flags risky settings |
| Vulnerabilities | `/vulnerabilities-identified` | Findings list |
| Connect AI | `/connect-ai` | MCP setup for AI clients (read-only) |
| Service accounts | `/service-accounts`, `/service-accounts/{id}` | `atgsa-` machine keys |
| Profile | `/profile`, `/profile/activity` | User profile, activity history |
| Settings | `/settings` | API keys (`atgla-`) |
| Messages, Products | `/messages`, `/products` | In-app messages, product page |
| Admin | `/admin/dashboard`, `/admin/users`, `/admin/workspaces`, `/admin/notifications`, `/admin/settings` (tabs: storage, backups, domain, plugins, MCP) | Users, workspaces, roles, notification channels, S3/NFS/Azure storage, scheduled backups and restore, custom domain and HTTPS, plugins |
| Enterprise | `/entrpirse_console` (sic) | Enterprise console |
| White-label | `/{page}` (About, Features, FAQ, Support, Contact) | Branded public pages |

### Feature list
Versioned config backups; workspaces and three roles (super admin, admin, user); local, S3, NFS and Azure
storage with migration; scheduled backups and portal restore; notifications (Email, Teams, Slack, n8n,
Telegram, webhooks, SMS); AI config review (OpenAI, Anthropic, Gemini, Azure OpenAI, Mistral, Groq,
DeepSeek, OpenRouter, Ollama, LM Studio); read-only MCP server; SSO; custom domain and internal CA;
white-label; Redis write queue when MySQL is down (circuit breaker); offline licence files (`.atgl`).
Premium and 3.0 work in the repo: Remote Tunnel, Site-to-Site IPsec, point-to-site, relay, hops,
Agent Monitoring, Google Drive backups (in progress).

Theme: the Console has light mode only. No dark mode, so screenshots are light only.

---

## 3. How the CLI and Console relate

1. **Install the Console** once (Docker, one command). Create a personal API key and, for automation, a service account key.
2. **Install the CLI** on each Linux server. `login`, then `system register`.
3. **Back up** with `sync`. The Console keeps every version, compares them and runs AI review.
4. **Automate** with `schedule enable` (daily check and sync with the service account key).
5. **Operate** from the Console: browse versions, get notified, manage teams. Restore from the CLI with `config restore`.

Short form for the site: "See health on the server with the CLI. Keep history and control in the Console."
The CLI's health commands work with no Console at all.

---

## 4. Content Merge Map (app.atglance.live into atglance.live)

Finding: **app.atglance.live is a static downloads site, not the running Console.** No login, no accounts.
It is plain files on nginx (`/www/wwwroot/app.atglance.live/`). So it is merged fully (case 1 of the brief);
no "Launch Console" button is needed. Each customer hosts their own Console.

| Old URL | New location on atglance.live | Action |
|---|---|---|
| `app.atglance.live/` | `/downloads` | Rewrite (one page with CLI and Console) |
| `app.atglance.live/cli/` | `/downloads#cli`; its "What changed" text to `/releases/cli/2.2.0` | Rewrite |
| `app.atglance.live/console/` | `/downloads#console` | Rewrite |
| `app.atglance.live/cli/atglance_2.2/*` (8 packages, `SHA256SUMS`) | `/downloads/files/cli/2.2.0/*` | Keep file, 301 old path |
| `app.atglance.live/cli/atglance_2.0/*`, `cli/atglance_1.2.deb`, `cli/upgrade/`, `cli/downgrade/` | `/downloads/files/cli/<version>/*` | Keep file, 301 old path |
| `app.atglance.live/console/atglance-installer.sh`, `.ps1` | `/downloads/files/console/atglance-installer.sh`, `.ps1` | Keep file, 301 (`curl -fsSL` and `irm` follow redirects) |
| `app.atglance.live/console/atglance_update.sh`, `.ps1` | `/downloads/files/console/atglance_update.sh`, `.ps1` | Keep file, 301 |
| `app.atglance.live/console/atglance-updater.sh`, `.ps1` | same as above | **Broken today**: the page links these names, but the server answers 301 to `/dashboard`. The files are named `atglance_update.*`. Fix with the redirect. |
| `app.atglance.live/console/Old-V1/` | ? | Ask (contents unknown to me) |
| `app.atglance.live/sitemap.xml`, `robots.txt` | `/sitemap.xml`, `/robots.txt` | 301 (today they go to `/dashboard`) |
| `app.atglance.live/404.html`, `502.html`, `cli/untitled directory` | none | Drop |

No CLI or Console script hardcodes `app.atglance.live`, so the redirects break nothing in the installers.
Redirects go in the nginx server block for `app.atglance.live` (one `return 301` map). The subdomain stays
up only to serve redirects.

### Existing atglance.live pages in the new structure
| Page | Plan |
|---|---|
| `/` | Rewrite: new hero, how it works, CLI and Console sections, comparison, quick start |
| `/cli`, `/console` | Rewrite: CLI install tabs, feature grid, terminal demo, searchable command reference; Console guided tour with callouts and lightbox |
| `/releases`, `/releases/{product}/{version}` | Keep the URLs, redesign. Source moves to content files |
| `/roadmap` | New |
| `/downloads` | New (replaces app.atglance.live) |
| `/docs/*` | Keep, restyle |
| `/product`, `/architecture`, `/use-cases`, `/security` | Proposal: merge Product into Home (301), keep the other three restyled |
| `/pricing`, `/faq`, `/about`, `/contact`, `/terms`, `/privacy`, `/known-problems`, `/features/remote-tunnel` | Keep, restyle; FAQ moves to a content file |
| `/login`, `/register`, `/dashboard/*`, Filament admin | Unchanged |

Top nav: Home · CLI · Console · Downloads · Releases · Roadmap · Docs · Pricing, plus Sign in.
No GitHub link: the CLI repo has no public URL in its README. The Console repo is
`github.com/niketchandra/atGlance-managementGUI`.

---

## 5. Inconsistencies found (fix during the redesign)

1. Broken Console updater links on app.atglance.live (see the merge map).
2. Console version: README says 2.1.2, `config/app.php` says 2.4.0, CHANGELOG marks 2.3.0 "unreleased", the site says 2.3.0 is released.
3. The /cli page sample prints `atglance v1.0.0`, the banner says v2.2.0.
4. The Console README tells users to run `sudo atglance --configure` (a 1.x flag). v2 uses `login` and `system register`.
5. app.atglance.live says the CLI is for "Ubuntu and Debian"; it supports any systemd Linux.
6. Pricing says Free has up to 5 nodes; the FAQ says "no system limits".
7. SSO provider lists differ across /console, /product and /pricing.
8. "No telemetry" claims next to privacy text about diagnostic data.
9. Typos: "Registerd Nodes", "Upto", "Warreny", "Rest API API Gateway", stray "s." on About; title "Operations, AtGlance".
10. The Console release badge for v2.2.0 says "Feature" although its text calls it a security release.
11. `/releases` and its pages are missing from `sitemap.xml`.

---

## 6. Open questions

1. **Releases as files.** Today releases live in a database settings row, are published from the Filament admin, and email all users on publish (`ReleaseNotifier`). Moving to `content/releases/<product>/<version>.md` means a release is published by commit and deploy. Keep the email by sending it from a command (`php artisan releases:notify cli 2.2.0`)? Or keep the admin editor and only export to files?
2. **Roadmap items.** No roadmap exists anywhere. Candidates from the repos (to confirm): CLI 3.0 agent (Windows, macOS), Agent Monitoring, Remote Tunnel v2, Google Drive backups, production web server (Console issue #8), cloud-native access. Which are public, and with which status?
3. **Install tabs.** The brief asks for Windows / macOS / Linux. The CLI is Linux only (deb / rpm / tar.gz). Only the 3.0 agent runs on Windows and macOS, and it is unreleased. Proposal: tabs Debian/Ubuntu · RHEL/Fedora · Other Linux, and a "Windows and macOS: agent in 3.0" note.
4. **Download files.** Move the package files to atglance.live (`/downloads/files/...`) as in the map, or keep them on the subdomain and only move the pages?
5. **`console/Old-V1/`**: keep, move or drop?
6. **Console screenshots.** A Console runs locally on `localhost:9000` (stack `t23`). May I use it with a new seeded demo workspace, or should I start a fresh throwaway stack only for screenshots?
7. **GitHub link** in the nav: which repo, or none?
8. **Product page**: merge into Home (with a 301)?
