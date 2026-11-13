# CLI reference

Reference for the `atglance` v2 command line tool (version `2.2.0`).

`atglance` shows the health of the systemd services on a Linux server: whether each service is running, how long it has been up, which ports it listens on, and whether it logged errors or warnings in the last 24 hours. It installs no background service and uses no resources when it is not running.

The full manual is also installed with the package: `man atglance`.

[TOC]

## Quick reference

| Command | Purpose | Needs sudo? |
|---|---|---|
| `atglance status` | Running and failed services on one screen, in an application section and a system section | No (sudo adds ports of all services) |
| `atglance status --all` | Same, plus stopped services | No |
| `atglance status --type application` / `system` | Only application services or only system services | No |
| `atglance service <name>` | One service in detail, with a 24-hour log summary | No (sudo shows all logs) |
| `atglance export` | Every loaded service as JSON | No |
| `atglance export --logs` | Same, plus a log summary per service (slower) | No |
| `atglance watch` | Live status view that refreshes every 5 seconds | No |
| `atglance version` | Print the installed version | No |
| `atglance version --json` | One JSON line for the console: version, os, arch, server, system_id, registered, service_account (never a key); works without root and when the host is not registered | No |
| `atglance help` | Print usage and exit codes | No |
| `atglance login [--server URL]` | Connect this host to an AtGlance console | Yes |
| `atglance logout` | Forget the saved API token | Yes |
| `atglance whoami` | Show the console user of the saved token | Yes |
| `atglance check` (alias `doctor`) | Check the console connection, token, registration, systemd, journalctl, key expiry and the schedule | Yes |
| `atglance check --scheduled` | Check the stored keys only and send expiry notices (what cron runs) | Yes |
| `atglance schedule enable` / `disable` / `status` | Turn the daily check and sync on or off, or show them | Yes |
| `atglance system register` | Register this host in the console (`--service-account` also stores the scheduled-run key) | Yes |
| `atglance system status` | Show this host's system as the console sees it | Yes |
| `atglance system deregister` / `reactivate` | Set this host's system inactive / active again (your password or PIN) | Yes |
| `atglance system deregister --force` / `reactivate --force` | Same, with an admin's email and password or PIN | Yes |
| `atglance system rotate-key` | Replace the system key (consoles with API v1) | Yes |
| `atglance system service-account set` / `remove` | Set, replace or detach the service account this registered system runs as, without registering again | Yes |
| `atglance system enroll` | Store the console URL, system identity and service account key a console sent (JSON on stdin); used by consoles that install the CLI | Yes |
| `atglance config backup <svc>` / `--all` | Upload a service's unit file to the console (queued when the console is down; unchanged files are skipped, `--force` uploads anyway) | Yes |
| `atglance config list <svc>` | List the versions the console holds for a service | Yes |
| `atglance config pull <id>` | Download a version to a file | Yes |
| `atglance config diff <id>` | Compare a version with the live unit file (exit 3 if they differ) | Yes |
| `atglance config restore <svc> <id>` | Put a version of that service back, restart it, roll back if it fails | Yes |
| `atglance sync` | Upload queued backups, then back up every running or failed service whose unit file changed | Yes |
| `atglance sync --scheduled` | Same, with the service-account key and no local copies (what cron runs) | Yes |
| `atglance security events` | The organisation's security log: failed and locked force actions (admins) | Yes |
| `atglance security lockouts` | Admin accounts whose force actions are locked (admins) | Yes |

## Workflows

Step-by-step recipes for common tasks.

### Install or upgrade

Download the package for your system from [Downloads](/downloads#cli). Every file has a published SHA-256 checksum.


```bash
# Debian, Ubuntu, Raspberry Pi OS (pick your architecture: amd64, arm64 or armhf)
sudo apt-get install -y ./atglance_2.2.0_arm64.deb

# RHEL, Rocky, Alma, Fedora, openSUSE (x86_64 or aarch64)
sudo rpm -Uvh atglance-2.2.0-1.aarch64.rpm

# Any other Linux: unpack and copy the binary
tar -xzf atglance_2.2.0_linux_amd64.tar.gz
sudo cp atglance_2.2.0_linux_amd64/atglance /usr/local/bin/

atglance version
```

The package has no dependencies. Upgrading over a 1.x install is a normal `apt-get install` of the new package; the 1.x Python files are replaced.

### Check a server's health

```bash
atglance status                # running and failed services
sudo atglance status           # same, with the ports of every service
atglance status --all          # include stopped services
atglance service nginx         # one service: health, uptime, ports, last 24 h of errors and warnings
atglance watch                 # live view; Ctrl+C to stop
```

Look at the `HEALTH` column first: `Failed` and `Restarting` need attention, `Error` and `Warning` (in `service`) point at the journal.

### Use atglance in monitoring or scripts

```bash
atglance status >/dev/null; echo $?          # 0 ok, 3 a service failed or is restarting
atglance status -o json | jq '.services[] | select(.health=="failed") | .name'
atglance export > /var/tmp/services.json     # full snapshot, all loaded services
atglance export --logs > /var/tmp/services-with-logs.json   # slower: reads the journal per service
```

Output is plain text without colours when it goes to a pipe or file, and with `NO_COLOR=1`. JSON has `"schema_version": 2`.

### Connect a new host to the console

1. Create an API token in the console: **Settings > API keys**. It starts with `atgla-`.
2. Log in on the host:
   ```bash
   sudo atglance login
   # Console URL (https:// recommended; http:// also works) [http://localhost:8002]: https://console.example.com
   # API token (atgla-...): (hidden)
   # logged in to https://console.example.com as jane (API v1)
   ```
   With an `http://` URL, login also prints:
   `note: http:// sends your API key and passwords unencrypted; use https:// when the console supports it. To switch later: sudo atglance login --server https://<host>`
3. Check everything:
   ```bash
   sudo atglance check
   ```
4. Register the host:
   ```bash
   sudo atglance system register --tags prod --workspace 3
   # registered as system 4057010410 (web-01, status active)
   ```
   The name defaults to the hostname (`--name` to change it). The host generates its own system key and keeps it in `/var/lib/atglance/identity.json` (mode 0600); it is never printed.

### Move a 1.x host to v2

1. Install the v2 package over 1.x (see *Install or upgrade*).
2. Run any console command with sudo, for example `sudo atglance whoami`. The first run imports the 1.x settings:
   `note: moved 1.x settings from /home/pi/.config/atglance/config.json to /etc/atglance/config.json (old files left in place)`
3. The host keeps its system id and key, so it does not register again. A 1.x URL without a port (for example `http://192.168.1.7`) becomes `http://192.168.1.7:8002`, as 1.x did.
4. Confirm with `sudo atglance check`: the `system` line should show the host as `active`.

The 1.x files (`~/.config/atglance/config.json`, `atgla_validation_hash` in `/etc/environment`) are left untouched, so reinstalling the 1.x package brings the old setup back.

### Log in from a script or automation

```bash
# token piped on stdin from a protected file; without --server the saved, imported or default URL is used
sudo cat /root/atglance.token | sudo atglance login --server https://console.example.com

# token from the environment, read from a protected file (login saves it; other commands use it once)
export ATGLANCE_TOKEN="$(sudo cat /root/atglance.token)"
sudo --preserve-env=ATGLANCE_TOKEN atglance login --server https://console.example.com
```

Do not type the token itself into a command: it ends up in the shell history and the process list. atglance never accepts it as an argument.

`sudo` drops `ATGLANCE_TOKEN` from the environment. Pass it through with `sudo --preserve-env=ATGLANCE_TOKEN atglance ...` or `sudo ATGLANCE_TOKEN=... atglance ...` (the second form puts the token in the process list).

### Troubleshoot the console connection

```bash
sudo atglance check           # one line per check: ok, warn or fail; exit 1 on any fail
sudo atglance -v whoami        # adds one debug line per request (no tokens, keys or query strings)
```

| `check` says | What to do |
|---|---|
| `fail settings ... run: sudo chmod 600 ...` | Run the shown `chmod`; the file holds the API token |
| `fail settings not logged in` | `sudo atglance login` |
| `fail console ... cannot reach the console` | Check the URL and port (`:8002` for the gateway), firewall, and that the console is running |
| `warn console ... old API only` | The console is older than API v1; it still works, but upgrade the console |
| `fail token ... rejected the token` | The token is invalid, revoked or expired: create a new one and `sudo atglance login` |
| `warn clock ... differs from the console` | Fix time sync (NTP) on the host |
| `warn system not registered` | `sudo atglance system register` |
| `warn system ... is inactive` | The host is deregistered in the console; `sudo atglance system reactivate` (or reactivate it in the console) |
| `warn journal journalctl not found` | Log counts show as unknown; install systemd's journal tools |

### Switch to another console or token

```bash
sudo atglance login --server https://console.example.com:8002   # replaces URL and token after both check out
sudo atglance logout                                            # removes the token; system id and key stay
```

`logout` does not revoke the token in the console; revoke it there under **Settings > API keys**.

### Back up and restore service configuration

These commands need a console with API v1. On an older console they print `this needs a console with API v1; upgrade the console`.

```bash
sudo atglance config backup ssh        # upload the ssh.service unit file (queued if the console is down)
sudo atglance config backup --all      # every running or failed service
sudo atglance config list ssh          # versions stored in the console
sudo atglance config diff <id>         # compare a stored version with the live file
sudo atglance config restore ssh <id>  # put it back, restart the service, roll back if the restart fails
sudo atglance sync                     # upload backups queued while the console was down
```

If the console is down, `config backup` queues the file and exits 0. Run `sudo atglance sync` later. Other console commands print `note: N backups waiting; run 'sudo atglance sync'` while the queue is not empty, and `atglance check` shows a `queue` line (`warn queue cannot read <dir>: ...` when the queue directory cannot be read).

Details for each command are under "Config commands" below.

### Schedule a daily check and sync

```bash
sudo atglance system register --service-account   # asks for the service account key (atgsa-...)
# or, on a host that is already registered:
sudo atglance system service-account set          # asks for the service account key (atgsa-...)
sudo atglance schedule enable                     # check daily at 06:MM, sync daily at 02:MM or 03:MM
sudo atglance schedule status                     # times and the last 5 log lines
sudo atglance check                               # the pat, sa and schedule lines should be ok
```

A host that is already registered uses `system service-account set`; it keeps its system id, key and config history. On a console without that route, `register --force-new --service-account` registers again with the key instead (the old system is marked inactive when the identity records the same console). Turn it off with `sudo atglance schedule disable`.

### Remove atglance

```bash
sudo apt-get remove atglance            # or: sudo rpm -e atglance
sudo rm -rf /etc/atglance /var/lib/atglance   # only if you also want to drop the console login and system identity
```

Removing `/var/lib/atglance/identity.json` loses the host's system key; the host would then have to register again.

## Commands

### `atglance status`

Shows running and failed services in a table.

```
atglance status [--all] [--type all|system|application] [-o table|json]
```

| Option | Meaning |
|---|---|
| `--all` | Also show stopped (inactive) services. |
| `--type` | `system` or `application` shows only that kind of service; `all` (default) shows both. The exit code then reflects only the services shown. When nothing matches, prints `No application services` (or `No system services`). |
| `-o table` | Table output (default). |
| `-o json` | JSON output, same format as `export`. |

The table has one section per type: `Application services (N)` first, then `System services (N)`. Empty sections are left out. Each section has the columns `SERVICE`, `STATE` (systemd active/sub state), `UPTIME`, `PORTS` and `HEALTH`, aligned across sections, and lists its services alphabetically.

```
Application services (2)
SERVICE         STATE           UPTIME  PORTS   HEALTH
docker.service  active/running  3d 4h   -       Working
nginx.service   active/running  3d 4h   tcp/80  Working

System services (2)
SERVICE         STATE           UPTIME  PORTS   HEALTH
cron.service    active/running  3d 4h   -       Working
ssh.service     active/running  3d 4h   tcp/22  Working
```

The type is `system` for the operating system's own services or `application` for what the host runs (web servers, databases, containers, your own units):

- a unit file that no package ships (for example one you wrote in `/etc/systemd/system/`) is `application`;
- a unit from a Debian package with priority `required`, `important` or `standard` is `system`;
- otherwise the name decides: systemd, ssh, cron, networking, package tools, cloud-init, snapd, colord, Raspberry Pi and similar OS helpers are `system`, everything else `application` (including `cups`, `cups-browsed` and `triggerhappy` since 2.2.0).

The package database is read once per run (two `dpkg-query` calls). Without `dpkg` (non-Debian systems) only the name is used. `-o json` has the same value in each service's `category`. `config backup` and `sync` send it to the console with each upload (console 2.3 and later), where the configuration backups can be filtered by type.

Services that systemd lists but cannot find (`not-found`) are never shown.

Without sudo, `PORTS` shows `-` for services owned by other users, and this note is printed on stderr:

```
note: run with sudo to see ports of all services
```

Examples:

```
atglance status
sudo atglance status --all
atglance status -o json > services.json
atglance status --type application
```

### `atglance service <name>`

Shows one service in detail.

```
atglance service <name> [-o table|json]
```

`<name>` can be given without `.service`: `ssh` means `ssh.service`, `getty@tty1` means `getty@tty1.service`. Services that are stopped and not loaded in memory are still found.

The detail view shows the service name, type (`Type:` system or application), state, health and its reason, uptime, restart count, main PID, unit file path and listening ports. It also shows the number of errors and warnings in the journal in the last 24 hours, with the most recent of each.

Log reading is capped at 5000 journal entries, so a very noisy service cannot make the command slow. When the cap is reached, counts are shown with a `+` (for example `5000+`).

Notes printed on stderr when logs cannot be read:

| Note | Cause |
|---|---|
| `note: could not read the journal for this service (run with sudo to see its logs)` | The current user is not allowed to read the system journal. |
| `note: journalctl not found; log counts unknown` | `journalctl` is not installed. |
| `Note: run with sudo to see all logs` (in the output) | The user can read only part of the journal, so counts may be low. |

Examples:

```
atglance service ssh
sudo atglance service nginx
atglance service cron -o json
```

### `atglance export`

Prints every loaded service, including stopped ones, as JSON.

```
atglance export [--logs] [--type all|system|application]
```

| Option | Meaning |
|---|---|
| `--logs` | Add a 24-hour error and warning summary for each service. This runs `journalctl` once per service, so it is slower. |
| `--type` | `system` or `application` exports only that kind of service; `all` (default) exports both. |

`export` always exits 0 when it succeeds, even if a service has failed. Use `status` for health checks.

JSON format (`schema_version` 2, stable within v2):

```json
{
  "schema_version": 2,
  "hostname": "pi",
  "timestamp": "2026-10-11T10:00:00+05:30",
  "services": [
    {
      "name": "ssh.service",
      "active_state": "active",
      "sub_state": "running",
      "health": "working",
      "health_reason": "Running for 15 minutes or more",
      "uptime_seconds": 7500,
      "restarts": 0,
      "main_pid": 455,
      "unit_path": "/usr/lib/systemd/system/ssh.service",
      "ports": ["tcp/22"],
      "logs": {
        "errors": 0,
        "warnings": 2,
        "last_warning": "example warning text",
        "capped": false,
        "partial": false
      }
    }
  ]
}
```

- `uptime_seconds` is `null` when the start time is unknown.
- `main_pid` is `0` and `unit_path` is `""` when unknown; both keys are always present.
- `logs` is present only with `--logs` (and in `service -o json`).

### `atglance watch`

Shows the status table and refreshes it until you press Ctrl+C.

```
atglance watch [--interval 5s] [--all] [--type all|system|application]
```

| Option | Meaning |
|---|---|
| `--interval` | Time between refreshes. Accepts `5s`, `1m`, `250ms`, or a plain number of seconds such as `5`. The minimum is 1 second; smaller values are raised to 1 second. Default `5s`. |
| `--all` | Also show stopped services. |
| `--type` | `system` or `application` shows only that section; `all` (default) shows both. |

The table has the same sections as `status`.

`watch` lowers its own CPU priority (nice 10) so it never competes with the server's real work. On a Raspberry Pi it uses about 1 % of one CPU core at the default interval.

On a terminal, `watch` clears the screen before each refresh. When output goes to a file or pipe, or when `NO_COLOR` is set, it prints each refresh below the previous one instead.

### `atglance version`

Prints the installed version, for example `2.2.0`.

### `atglance help`

Prints the usage summary and exit codes. `atglance -h` and `atglance --help` do the same. Each command also accepts `-h` to show its own options, for example `atglance status -h`.

## Console commands (run with sudo)

### `atglance login [--server URL]`

Connects this host to an AtGlance management console and saves the connection in `/etc/atglance/config.json` (mode 0600).

- Asks for the console URL when run in a terminal. When the token is piped in (stdin is not a terminal), it does not ask: it uses the saved URL, else the URL from a 1.x install, else `http://localhost:8002`, and says so on stderr; pass `--server` to choose another. The default in brackets is the saved URL, else the URL from a 1.x install, else `http://localhost:8002`. `192.168.1.7:8002` is accepted and becomes `http://192.168.1.7:8002`; a URL with a path is refused.
- The console works over `https://` and `http://`. Use `https://` when the console has a certificate: over `http://` the API key, system key, passwords and unit files are not encrypted, and login prints `note: http:// sends your API key and passwords unencrypted; use https:// when the console supports it. To switch later: sudo atglance login --server https://<host>`. To change the URL later (for example from http to https), run `sudo atglance login --server <new URL>` again; on the same console the host keeps its system.
- Asks for the API token without showing it. Create the token in the console under Settings > API keys; it starts with `atgla-`. Instead of typing it you can set `ATGLANCE_TOKEN`, or pipe it in on stdin (for example `echo atgla-... | sudo atglance login --server https://console.example.com`).
- Checks which API the console offers (`/api/v1` on new consoles, the 1.x endpoints on older ones), checks the token, and saves only when both work.
- When the URL differs from the one the host's system was registered at, `login` first asks the new console for the system (with the system id and key). If the console has it, active or deregistered (the same console on a new address, port or scheme), the identity is kept, only its saved URL changes, and `login` prints `note: URL knows system 123; kept it (it was registered at OLD)`.
- Otherwise the system belongs to another console, and `login` moves `identity.json`, `service-account.json` and `state/last-pushed.json` aside (not `register-pending.json`), renaming each to `<file>.<host>-<UTC time>.bak` and prints `note: this host's system 123 belongs to SERVER; moved it to PATH. run 'sudo atglance system register' for URL`. When it cannot tell which console the system belongs to (an imported 1.x identity), it prints a note suggesting `sudo atglance system register --force-new` if `check` reports the system unknown.
- The move waits for a running sync or `config backup` (up to 60 seconds) and otherwise fails with `another sync is running` before saving anything.

### `atglance logout`

Removes the token from `/etc/atglance/config.json`. The host's system id and key are kept, so a later `login` continues with the same system. The token itself stays valid in the console until you revoke it there.

### `atglance whoami`

Shows the console user the token belongs to, the token's name and expiry, and the console URL and API in use.

### `atglance check` (alias `doctor`)

Checks everything a console command needs and prints one line per check (`ok`, `warn` or `fail`): running as root, the settings file and that it is not writable by other users, the console (reachable, API version), the token, the clock difference with the console, the host's registration, systemd, journalctl and the offline queue. Three more lines cover the keys and the schedule. Exits 1 when any check fails.

- `pat` shows the personal API token by name and last four characters with the days left (`"name" (…abcd) 40 days left`, or `no expiry` for a key without an expiry date); `warn` when it is expiring (15 days or fewer), `warn pat not logged in` without one.
- `sa` does the same for the service account key (`warn sa no service account key; run 'sudo atglance system service-account set'` without one; `warn sa not checked; log in first` when there is no login token).
- `schedule` is `ok schedule cron enabled (check HH:MM, sync HH:MM)`, or `warn schedule not enabled; run 'sudo atglance schedule enable'`.
- On a console older than API v1 both lines are `warn ... not checked; the console API is too old`.
- Keys are never printed in full.

### `atglance check --scheduled`

The command cron runs every day. It checks the stored keys only and asks the console to send expiry notices. It prints one line per key: `pat: ok N days left` (`pat: ok no expiry` for a key without an expiry date), `pat: notice sent (stage N)`, `pat: already sent`, `pat: not expiring` or `pat: expiry notice failed: <reason>`, and the same for `sa`. On a console older than API v1 it prints `pat: not checked; the console API is too old` (and the same for `sa`) and exits 0 for that. It prints `no stored keys to check` and exits 1 when there are none, and `check --scheduled needs root; run it with sudo` without root.

### `atglance schedule enable|disable|status`

Run with sudo. Manages `/etc/cron.d/atglance`, which runs `atglance check --scheduled` daily at 06:MM and `atglance sync --scheduled` daily at 02:MM or 03:MM (minutes picked at random), both as root with output sent to the log under the tag `atglance` (`logger -t atglance`). Each line starts with `[ -x /usr/bin/atglance ] &&`, so nothing runs (and nothing is logged) after the package is removed but the file is left behind. `enable` on a file in the older format keeps its times and rewrites it.

- `enable` needs a service account key (`no service account key; run 'sudo atglance system service-account set' first (on an older console: 'sudo atglance system register --force-new --service-account')`) and cron (`cron is not installed; install the cron package`). It prints `enabled: check daily at 06:MM, sync daily at HH:MM`. Running it again keeps the times when the existing file is recognised (a file with no check/sync lines is replaced with new times) and prints `already enabled: check daily at ..., sync daily at ...`.
- `disable` removes the file and prints `disabled`, or `not enabled`.
- `status` prints `enabled: check daily at ..., sync daily at ...` or `disabled; run 'sudo atglance schedule enable'`, and a `recent runs:` list of the last 5 log lines (from `journalctl -t atglance`, else `/var/log/syslog`).
- Removing the package with `purge` deletes the schedule file.

### `atglance system register [--name N] [--tags a,b] [--workspace ID] [--ip ADDRESS] [--force-new] [--service-account]`

Registers this host in the console and saves the system id and a newly generated system key in `/var/lib/atglance/identity.json`. Sends the name (default: the hostname), `linux`, the address this host uses to reach the console, the distribution and version from `/etc/os-release`, the tags, the workspace and the hostname and CPU architecture as metadata.

- `--ip ADDRESS` sets the address the console shows for this host. Without it, atglance uses the address it uses to reach the console. If it finds none, it refuses with `could not find this host's IP address; pass --ip ADDRESS` (exit 2).
- Keeps the new key in `/var/lib/atglance/register-pending.json` until the identity is saved. If the answer is lost (network drop, timeout) or is a 5xx error, run the same command again: it reuses the same key, and a console with idempotent register returns the same system. Only a 4xx answer removes the pending key.
- `--service-account` asks for a service account key (`atgsa-...`, hidden input; anything else is refused with `the service account key must start with atgsa-`), sends it with the registration and saves it after the identity (`warning: registered, but saving the service account key failed: <err>; scheduled runs will not work` if that fails). Scheduled runs and `schedule enable` need it.
- Refuses when the host is already registered; `--force-new` registers it again as a new system. When the old system belongs to the same console, the console retires it: `system OLD marked inactive in the console; registered as NEW`, or `note: the console did not retire system OLD; deregister it there` when it did not. When the console cannot find the old system (HTTP 422), atglance registers again without retiring it, with the same key, and prints `note: the console did not accept the old system OLD for retirement; it stays active — deregister it there`. When the identity does not record its console (a 1.x import) or names another one, nothing is retired and the old system stays in the console.
- After a successful registration, `/var/lib/atglance/state/last-pushed.json` is removed, so the next backup or sync uploads every config to the new system.
- Refuses when a 1.x install had registered the host but its key (`atgla_validation_hash` in `/etc/environment`) was not found, because registering again would create a second system. The message says how to recover the old system or to go ahead with `--force-new`.

### `atglance system status`

Shows this host's system as the console sees it: id, name, status, workspace and registration time. It also fills in the name and registration time that a 1.x import leaves empty in `identity.json`.

### `atglance system deregister [--pin]` and `atglance system reactivate [--pin]`

Sets the system inactive or active again. Asks for your console password without showing it (`--pin`: your PIN instead). Only the system's owner or an admin of its organisation may do this. A deregistered system can still be looked at, but the console refuses new config backups until it is reactivated.

### `atglance system deregister --force [--id ID] [--pin]` and `atglance system reactivate --force [--id ID] [--pin]`

The same change made by an admin of the system's organisation: asks for the admin's email and password (or PIN). Does not need a token, so it works after `logout`. `--id` acts on another system; the default is this host's.

After repeated failed attempts the console locks force actions for that admin account for a while (HTTP 423). atglance then prints `the console locked force actions for this admin account after repeated failed attempts: <console message>` (the message says until when), exits 1 and does not retry.

### `atglance system rotate-key [--pin]`

Generates a new system key, asks the console to switch to it (your password or PIN), and saves it only when the console has accepted it. Needs a console with API v1; older consoles answer `this needs a console with API v1; upgrade the console`.

The new key is stored as `pending_key` in `identity.json` before the call. If the answer is lost or is a 5xx error, run `sudo atglance system status`: it tries both keys and keeps the one the console accepts. A 4xx answer removes it. A later `rotate-key` first resolves a pending key.

### `atglance system enroll`

For consoles that install the CLI on a host themselves (over SSH or WinRM) and register the system on the console side. The console runs `atglance system enroll` and writes one JSON object to its stdin; the host ends up with the console URL, the system identity and the service account key, and no personal API key (so `whoami` and the commands that need a login report `not logged in`; scheduled runs use the service account key).

```
{"server": "https://console.example", "system_id": "42", "system_key": "...", "system_name": "web-1", "service_account_key": "atgsa-..."}
```

All fields except `system_name` are required. `system_id` is normally a string; a JSON number is accepted and stored as its decimal string. The server URL is normalised as for `login`. Nothing is taken from the command line and no key is ever printed.

- Writes `config.json` (server; no token), `identity.json` (status `active`, registered now, console URL) and `service-account.json` (0600). Prints `enrolled system <id> for <server>` and exits 0.
- When `identity.json` belongs to another console, `identity.json`, `service-account.json` and `state/last-pushed.json` are moved aside first, exactly as `login` does (see below), and the same `note: ...moved it to PATH...` is printed.
- Exit 2: any argument (refused without echoing it, so `enroll atgsa-...` never prints the key), empty or invalid JSON, more than one JSON object, a missing `server`, `system_id`, `system_key` or `service_account_key`, or a service account key that does not start with `atgsa-`. Nothing is written.
- Exit 1: a save failed, or the command is not run as root / from an elevated prompt (`this command needs root; run it with sudo`).

### `atglance system service-account set` and `atglance system service-account remove`

Changes the service account that this registered system runs as for scheduled runs, without registering again: the system keeps its id, its system key and its config history. Use it to add a service account later or to replace an expiring service account key. Needs your login token (a person's key, `atgla-`) and a console that has the route (console 2.1.2 with the 2026-10 security update).

If the console answers 404 with no message (an older console, or a proxy in front of it that does not pass `/api/v1` through), the command says so: `the console answered 404 with no message: an older console, or a proxy that does not pass /api/v1 through; ...`.

- `set` asks for the service account key (`Service account key (atgsa-...)`, hidden; read from stdin when piped; never taken from the command line, so `set atgsa-...` is refused with exit 2 without echoing it). A key that does not start with `atgsa-` is refused with `the service account key must start with atgsa-` (exit 2). The key is sent as `X-Atglance-Service-Account-Key`; after the console's `200` it is saved to `/var/lib/atglance/service-account.json` (0600) and atglance prints `service account set for system <id>; scheduled runs use it`. From that moment the previous service account key no longer works for this system.
- If saving fails after the console accepted the key, atglance prints `the console now expects this service account for system <id>, but saving <file> failed: ...` and tells you to fix the problem and run `sudo atglance system service-account set` again (sending the same key again changes nothing in the console).
- `remove` asks the console to detach the service account and, after its `200`, deletes `service-account.json` and prints `service account removed`. Scheduled runs then fail until a new key is set.
- Only the system's owner, a workspace admin of its workspace or an admin of its organisation may do this (`403` with the console's message otherwise). A key that is invalid, revoked, expired, disabled or of another organisation is reported as `the console rejected the service account key atgsa-****abcd ...` (last four characters only); a service account without `systems.register` in the system's workspace gets the console's `403` message.
- On a console without the route (HTTP 404 or 405): `this needs a newer console; use 'sudo atglance system register --force-new --service-account'`. On a legacy console: `this needs a console with API v1; upgrade the console`.

## Config commands (run with sudo)

These commands need a console with API v1. On a legacy console they print `this needs a console with API v1; upgrade the console`. Service names are sent without `.service`, so versions join the 1.x history of the same service.

### `atglance config backup <service> [--local] [--force]`

Uploads the service's unit file (the path systemd reports as `FragmentPath`) to the console as a new version. Files larger than 10 MB are refused.

Backup, sync, diff and restore only use a unit file that, after following symlinks, is a regular file owned by root under `/etc/systemd/`, `/run/systemd/`, `/usr/lib/systemd/`, `/lib/systemd/` or `/usr/local/lib/systemd/`. Anything else is refused with `<path> is not a regular file`, `<path> is not owned by root` or `<path> resolves outside the systemd unit directories (<target>)`, so a unit symlink cannot make the root sync upload or overwrite another file (such as `/etc/shadow`) or hang on a FIFO. `--all` and `sync` count such a unit as failed and go on with the others.

- A file that is the same as the last one pushed (kept in `/var/lib/atglance/state/last-pushed.json`), or the same as the newest version the console holds, is not uploaded: it prints `unchanged <unit>` and exits 0. `--force` uploads it anyway. A version deleted in the console stays "unchanged" until you use `--force`.
- When `last-pushed.json` has no entry for a service (a new host, or the file was lost), atglance asks the console once per run for the newest version of every service (`GET /api/v1/config-files?latest=1`) and compares the console's `sha256` with the local file, without downloading it; equal means `unchanged` and the entry is recorded. Versions stored before the console had hashes (`sha256` null, until an admin runs `php artisan atglance:config-hash-backfill`), or a console that cannot answer that request, fall back to downloading the newest version and comparing it. If the console reports a different `sha256` for a file it just stored, atglance prints `warning: <unit>: the console's sha256 of version vN (id N) differs from the local file's; ...`.
- `--local` also keeps a copy at `/var/lib/atglance/backups/<service>/<unit>.<UTC time>` (file mode 0600, directory 0700). The copy is written before the upload.
- If the console cannot be reached, the file is queued, the command exits 0 and prints `note: console unreachable; queued (run 'sudo atglance sync')`.
- If the console says this system is deregistered (409), the command exits 1 and queues nothing. Run `sudo atglance system reactivate`.

### `atglance config backup --all [--local] [--force]`

Backs up every loaded service that is active, reloading, activating or failed and has a unit file. Prints `N uploaded, N unchanged, M queued, K failed` and exits 1 if K is above 0.

- After the console is unreachable once, the rest are queued without more requests, and the note is printed once.
- After a refusal (409, 401, 403 or 404), the remaining services count as failed and no more requests are sent.

### `atglance config list <service>`

Lists the versions the console holds for this host and service, with the columns `ID`, `VERSION`, `CREATED` and `BY`. When the console stores a type for the service, a line such as `nginx: application service` comes first; the console keeps an admin's choice over the type the CLI sends.

### `atglance config pull <id>`

Downloads a version and writes it to `/var/lib/atglance/pulled/<service>/<name>.<id>` (file mode 0600, directory 0700). Prints the path. File names from the console are cleaned first. The live unit file is not touched.

### `atglance config diff <id>`

Prints a unified diff between the stored version and the live unit file. Exit code 0 means they are identical, 3 means they differ. A diff of more than 2000 changed lines is shown as one block replacement to limit memory use. Control characters are printed in caret notation (`^[` for ESC).

### `atglance config restore <service> <id> [--yes] [--allow-ssh-restart]`

Puts a stored version back as the live unit file of the service you name. You must name the service: the unit that is written is the one installed under that name, never one the console chooses. The old form `config restore <id>` exits 2 with `config restore needs the service name: atglance config restore <service> <id>`. A version that belongs to another service is refused before anything is asked or written: `version <id> is a backup of <its service>, not <service>; nothing changed` (exit 1); an alias and its unit match (`sshd` and `ssh`). Steps:

1. Refuses if you run it in an SSH session (detected even under sudo: `SSH_CONNECTION` or `SSH_CLIENT` is set, or an `sshd` process is among its parents) and the unit is `ssh` or `sshd`, including aliases: `restoring the SSH service over SSH can lock you out; rerun with --allow-ssh-restart if you have console access`. `--yes` does not bypass this.
2. Shows the diff and asks you to confirm. `--yes` skips the question. If the stored version equals the live file it prints `identical to <file>; nothing to restore` and exits 0. If you answer no it prints `nothing changed` and exits 0. Control characters in the printed diff (such as ESC or CR) are shown as `^[`, `^M`; the written file keeps the exact bytes.
3. Copies the live file to `/var/lib/atglance/backups/<service>/` and keeps the copy. A second restore in the same second gets a `-N` suffix.
4. Writes the new file atomically and keeps the owner and mode. If the unit file is a symlink, atglance writes the target and keeps the link. The target must pass the unit file checks above (checked before the diff): a masked unit (`/dev/null`), a file not owned by root or a file outside the systemd unit directories is refused.
5. If the service was not running (inactive, failed, a oneshot unit), runs a systemd reload only, does not start it, prints `restored <file> from version <v>; <unit> was not running, so it was not started; previous file kept at <path>` and exits 0. The steps below apply to a running service.
6. Runs a systemd reload and restarts the service.
7. Waits up to 15 seconds. The restore succeeds when the unit stays active for 3 seconds.
8. Otherwise rolls back: old file, reload, restart. It prints `restore failed; previous file restored (backup kept at <path>)` and exits 1. It then waits for the service again under the same rules; if it does not come back it adds `warning: <unit> is not active after the rollback; check 'systemctl status <unit>'`.

If writing the new file fails, nothing changes and the service is not restarted.

### `atglance sync [--scheduled]`

Uploads queued backups, oldest first, and prints `queue: N uploaded, M waiting` when the queue was not empty. Then it backs up every running or failed service whose unit file changed since the last push and prints `N uploaded, N unchanged, M queued, K failed`.

- Only one sync or `config backup` runs at a time (lock `/var/lib/atglance/sync.lock`); another one waits up to 60 seconds, then fails with `another sync is running`.
- It stops and keeps the remaining items when the console is unreachable or answers 401, 403, 404, 409, 429 or a 5xx error. It then backs up nothing new, so during a long outage each scheduled sync stops at the queue (exit 1) and files changed meanwhile are picked up by the first sync after the console is back.
- Any other 4xx answer drops that item and prints a message.
- Exits 1 if anything is still waiting or was dropped, or a backup failed.
- `--scheduled` is what cron runs: it authenticates with the service account key instead of the login token and keeps no local copies. A rejected service account key is named in the message by its last four characters (`service account key atgsa-****abcd`).

## Security (admins)

These commands read the console's security log with the saved API key. Only an admin of the organisation may use them (a super admin sees every organisation); anyone else gets `only an organisation admin can view security events (this token's user is not an admin)` and exit 1. They need a console with API v1; a legacy console answers `this needs a console with API v1; upgrade the console`. Text from the console is printed with control characters escaped.

### `atglance security events [--type T] [--since TIME] [--until TIME] [--user ID] [--all] [-o table|json]`

Lists security events, newest first: one page of 50 by default. Event types today are `force.failed`, `force.succeeded`, `force.locked` and `force.unlocked` (the console may prefix them with `security.`; they are shown as given).

- `--type` keeps one type, `--since` and `--until` a time range (passed to the console as written, for example `2026-10-01` or `2026-10-01T08:00:00Z`; the console answers `Invalid since parameter` for a value it cannot read), `--user` one user id.
- `--all` reads the following pages too, up to 20 pages (1000 events), then prints `note: stopped after 1000 events; narrow with --since`.
- The table has the columns `TIME` (UTC), `TYPE`, `USER` (`-` when none), `IP` and `DETAILS` (sorted `key=value` pairs, cut to 60 characters).
- `-o json` prints the events as a JSON array in the console's shape (`id`, `type`, `user`, `ip`, `details`, `created_at`).
- atglance asks for each page on the configured console URL; it does not follow the `next` link the console returns, which may name another host.

```bash
sudo atglance security events --type force.failed --since 2026-10-01
sudo atglance security events --all -o json > security.json
```

### `atglance security lockouts [-o table|json]`

Lists the accounts whose force actions are locked, with the columns `USER`, `EMAIL`, `LOCKED UNTIL` (UTC) and `LEVEL`. Prints `no accounts are locked` when there are none.

### Queue and files

| Path | Content |
|---|---|
| `/var/lib/atglance/queue/` (0700) | Queued backups: one `.data` file and one `.json` manifest per item. The manifest is written last. A `.data` file without a manifest that is older than 1 hour is removed. |
| `/var/lib/atglance/backups/<service>/` | Local copies from `--local` and the copies `config restore` makes. |
| `/var/lib/atglance/pulled/<service>/` | Files from `config pull`. |
| `/var/lib/atglance/state/last-pushed.json` | What `config backup` and `sync` last pushed per service (hash and version), used to skip unchanged files. |
| `/var/lib/atglance/service-account.json` | The service account key for scheduled runs (mode 0600). |
| `/var/lib/atglance/sync.lock` | Lock so only one sync runs at a time. |
| `/etc/cron.d/atglance` | The daily check and sync schedule from `schedule enable`; removed by `schedule disable` and when the package is purged. |

### Moving from 1.x

The first console command run with sudo imports the 1.x settings (`~/.config/atglance/config.json` of the sudo user, else `/root/.config/atglance/config.json`, and `atgla_validation_hash` from `/etc/environment`). The host keeps its system id and key, so it does not need to register again. The 1.x files are left in place.

### `-v`

`atglance -v <command>` prints one debug line per console request (method, host, path, status, time) on stderr. Tokens, keys and query strings are never printed.

## Agent (3.0)

The agent keeps one outbound, encrypted connection to the console (no open ports) and runs on Linux (systemd), Windows Server (service `AtGlanceAgent`) and macOS (launchd). It is part of CLI 3.0.

| Command | Purpose | Needs sudo? |
|---|---|---|
| `atglance agent install` / `uninstall [--purge]` | Install or remove the agent as a system service | Yes |
| `atglance agent start` / `stop` / `restart` | Control the agent service | Yes |
| `atglance agent status [-o json]` | Connection state of the agent | No |
| `atglance agent run` | Run the agent in the foreground | Yes |
| `atglance agent logs [-n 50] [-f]` | Show or follow the agent log | No |

On Windows Server, run these in an elevated PowerShell, after `atglance login` and `atglance system register`:

```powershell
atglance agent install      # service AtGlanceAgent: Automatic (Delayed Start), restarts on failure
atglance agent start
atglance agent status
```

On macOS:

```bash
sudo install -m 0755 atglance_3.0.0_darwin_arm64 /usr/local/bin/atglance
sudo atglance login --server https://console.example.com
sudo atglance system register
sudo atglance agent install     # writes /Library/LaunchDaemons/com.atglance.agent.plist
sudo atglance agent start
```

On Windows the health commands (`status`, `service`, `watch`, `export`) read the Service Control Manager. `config backup`, `restore`, `diff` and `sync` work on systemd unit files, so they are Linux only.

## Health states

The `HEALTH` column uses the first rule that matches:

| State | Meaning |
|---|---|
| Failed | systemd reports the service as failed. |
| Stopped | The service is not running (inactive). |
| Restarting | systemd is waiting to restart it (`auto-restart`), or it restarted 3 or more times and is still starting or has run for less than 15 minutes. |
| Starting | systemd is still starting the service (`activating`). |
| Error | Errors in the journal in the last 24 hours (`service` and `export --logs` only). |
| Warning | Warnings in the journal in the last 24 hours (`service` and `export --logs` only). |
| Done | A one-shot service that finished successfully. |
| Starting | Running for less than 15 minutes. |
| Working | Running normally for 15 minutes or more. |

Uptime is measured from the system's monotonic clock, so it stays correct when the wall clock jumps, for example on a Raspberry Pi that sets its clock from the network after boot.

## Exit codes

| Code | Meaning |
|---|---|
| 0 | Success. |
| 1 | Error, for example systemd is not reachable or the service does not exist. |
| 2 | Usage error: unknown command, bad option or unexpected argument. |
| 3 | Degraded: a shown service is Failed or Restarting (`status`, `service`). Also `config diff` when the files differ. |

Exit code 3 makes `atglance status` usable as a monitoring check:

```
atglance status >/dev/null; echo $?
```

## Old (1.x) flags that still work

These 1.x flags keep working in v2. Each prints a one-line note on stderr naming the new command.

| 1.x flag | v2 command |
|---|---|
| `atglance --show-my-services` | `atglance status` |
| `atglance --app <name>` or `--app=<name>` | `atglance service <name>` |
| `atglance --version` | `atglance version` |
| `atglance watch --interval 5` | Unchanged (plain seconds still accepted) |
| `atglance export` | Unchanged |
| `atglance --configure` or `configure` | `atglance login` |
| `atglance --validate` | `atglance whoami` |
| `atglance --system-register` | `atglance system register` |
| `atglance --system-deregister` | `atglance system deregister` |
| `atglance --system-deregister-force` | `atglance system deregister --force` |
| `atglance --system-reactivate` | `atglance system reactivate` |
| `atglance --system-reactivate-force` | `atglance system reactivate --force` |
| `atglance --system-regenerate-hash` | `atglance system rotate-key` |

## 1.x config flags replaced by config commands

These 1.x flags are replaced by the config commands. They print a usage error and exit 2:

| 1.x flag | v2 command |
|---|---|
| `--app <svc> --config-save` | `atglance config backup <svc>` |
| `--app <svc> --config-show` | `atglance config list <svc>` |
| `--app <svc> --config-import <id>` | `atglance config pull <id>` |
| `--restore -f` | `atglance config restore <service> <id>` |

The config commands work only with a console that has API v1. A server on a legacy console should stay on 1.2 for config backups. A service account key (`atgsa-`) is not a login token: register it with `system register --service-account` or, on a registered host, `system service-account set`; `atglance login` refuses it with `this is a service account key (atgsa-); log in with a personal API key (atgla-)`.

## Environment variables

| Variable | Effect |
|---|---|
| `NO_COLOR` | Any value turns off colour and screen clearing. |
| `TERM=dumb` | Same as `NO_COLOR`. |

Colour is also off whenever output goes to a file or pipe.

## Requirements

- Linux with systemd 230 or newer. On a system without systemd, commands exit 1 with `atglance: connect to systemd over D-Bus: ...`.
- No other packages: no Python, no libraries. `journalctl` (part of systemd) is used only for log counts.
