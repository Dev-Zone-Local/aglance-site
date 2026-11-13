# Screenshots

CLI captures (`cli/`) are SVG renders of real `atglance` 2.x output, copied from the CLI repo's `docs/images/v2/`. They only contain private Docker IPs and `example.test` addresses. Console captures (`console/`) are 1568×726 JPGs of a running Management Console 2.4.0 (local test install), taken on 2026-11-14. Before each capture the signed-in e-mail was replaced with `admin@example.com`, LAN addresses with `10.0.0.x`, and key-like form fields were blanked. Tabs that hold credentials (Storage, Email, AI Connect, Licence) were not captured.

| File | Command shown |
|---|---|
| `cli/cli-backup-deregistered.svg` | `atglance config backup atglance-demo --force` |
| `cli/cli-check-after-logout.svg` | `atglance check` |
| `cli/cli-check-before-login.svg` | `atglance check` |
| `cli/cli-check-registered.svg` | `atglance check` |
| `cli/cli-check-scheduled.svg` | `atglance check --scheduled` |
| `cli/cli-check.svg` | `atglance check` |
| `cli/cli-config-backup-all.svg` | `atglance config backup --all` |
| `cli/cli-config-backup-changed.svg` | `atglance config backup atglance-demo` |
| `cli/cli-config-backup-unchanged.svg` | `atglance config backup atglance-demo` |
| `cli/cli-config-backup.svg` | `atglance config backup atglance-demo` |
| `cli/cli-config-diff-same.svg` | `atglance config diff 2207` |
| `cli/cli-config-diff.svg` | `atglance config diff 2207` |
| `cli/cli-config-list.svg` | `atglance config list atglance-demo` |
| `cli/cli-config-pull.svg` | `atglance config pull 2207` |
| `cli/cli-config-restore.svg` | `atglance config restore atglance-demo 2207 --yes` |
| `cli/cli-deregister-final.svg` | `atglance system deregister` |
| `cli/cli-deregister-force.svg` | `atglance system deregister --force` |
| `cli/cli-deregister.svg` | `atglance system deregister` |
| `cli/cli-export-logs.svg` | `atglance export --logs` |
| `cli/cli-export.svg` | `atglance export` |
| `cli/cli-help.svg` | `atglance help` |
| `cli/cli-install.svg` | `apt-get install -y ./atglance_2.0.0_amd64.deb` |
| `cli/cli-legacy-flag.svg` | `atglance --show-my-services` |
| `cli/cli-login.svg` | `atglance login --server http://localhost:8002` |
| `cli/cli-logout.svg` | `atglance logout` |
| `cli/cli-reactivate-force.svg` | `atglance system reactivate --force` |
| `cli/cli-reactivate.svg` | `atglance system reactivate` |
| `cli/cli-register-again.svg` | `atglance system register` |
| `cli/cli-register.svg` | `atglance system register --name wsl-docs --tags docs,wsl --workspace 3 --service-account` |
| `cli/cli-rotate-key.svg` | `atglance system rotate-key` |
| `cli/cli-schedule-disable.svg` | `atglance schedule disable` |
| `cli/cli-schedule-enable.svg` | `atglance schedule enable` |
| `cli/cli-schedule-status.svg` | `atglance schedule status` |
| `cli/cli-security-events.svg` | `atglance security events` |
| `cli/cli-security-lockouts.svg` | `atglance security lockouts` |
| `cli/cli-service-json.svg` | `atglance service cron -o json` |
| `cli/cli-service.svg` | `atglance service cron` |
| `cli/cli-status-all.svg` | `atglance status --all` |
| `cli/cli-status-deregistered.svg` | `atglance system status` |
| `cli/cli-status-json.svg` | `atglance status -o json` |
| `cli/cli-status-sudo.svg` | `atglance status` |
| `cli/cli-status.svg` | `atglance status` |
| `cli/cli-sync-again.svg` | `atglance sync` |
| `cli/cli-sync-scheduled.svg` | `atglance sync --scheduled` |
| `cli/cli-sync.svg` | `atglance sync` |
| `cli/cli-system-status.svg` | `atglance system status` |
| `cli/cli-verbose.svg` | `atglance -v whoami` |
| `cli/cli-version.svg` | `atglance version` |
| `cli/cli-watch.svg` | `atglance watch` |
| `cli/cli-whoami-after-logout.svg` | `atglance whoami` |
| `cli/cli-whoami-before-login.svg` | `atglance whoami` |
| `cli/cli-whoami.svg` | `atglance whoami` |

| File | Console page |
|---|---|
| `console/console-dashboard.jpg` | Admin dashboard (`/admin/dashboard`) |
| `console/console-servers.jpg` | Servers: live agent monitoring (`/servers`) |
| `console/console-systems.jpg` | Systems registered (`/systems-registered`) |
| `console/console-config-backups.jpg` | Configuration backups (`/configuration-backups`) |
| `console/console-service-versions.jpg` | All versions of one service (ssh) |
| `console/console-config-file.jpg` | One configuration file with AI validation history |
| `console/console-mcp.jpg` | MCP: connect AI tools (`/connect-ai`) |
| `console/console-users.jpg` | Users (`/admin/users`) |
| `console/console-workspaces.jpg` | Manage workspaces (`/admin/workspaces`) |
| `console/console-enterprise-console.jpg` | Enterprise Console (`/entrpirse_console`) |
| `console/console-notifications.jpg` | Notifications (`/admin/notifications`) |
| `console/console-service-accounts.jpg` | Service accounts (`/admin/service-accounts`) |
| `console/console-settings-info.jpg` | Site settings: Info |
| `console/console-settings-agents.jpg` | Site settings: Agents |
| `console/console-settings-backups.jpg` | Site settings: Backup & Restore |
| `console/console-settings-plugins.jpg` | Site settings: Plugins |
| `console/console-remote-tunnel.jpg` | Remote Tunnel (`/admin/tunnel`) |
| `console/console-profile.jpg` | Your profile (`/profile`) |
