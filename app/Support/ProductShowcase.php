<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Editable content of the product pages (/console and /cli), stored in the settings rows
 * "showcase_console" and "showcase_cli" and edited under Admin → Content → Product pages.
 *
 * Shape of one product:
 *   hero:     {badge, title, subtitle, primary_label, primary_url, secondary_label, secondary_url,
 *              ticks: [string], image, capture_path, capture_hint}
 *   features_heading: {eyebrow, title, sub}
 *   features: [{icon, eyebrow, title, text, bullets: [string], image, caption, capture_path, capture_hint, visible}]
 *   extras_heading:   {eyebrow, title}
 *   extras:   [{icon, title, text}]
 *   gallery:  [{image, title, caption}]
 *   sections: {key: bool}   built-in blocks of the page (architecture, install, ...)
 *
 * Images are paths on the "public" disk under showcase/. Nothing saved yet = the defaults below.
 */
class ProductShowcase
{
    public const PRODUCTS = ['console' => 'Management Console', 'cli' => 'AtGlance CLI'];

    public const IMAGE_DIR = 'showcase';

    /** Fixed blocks each page can show or hide: key => label. */
    public const SECTIONS = [
        'console' => [
            'architecture' => 'Architecture diagram',
            'resilience' => 'Database outage flow',
            'rbac' => 'Roles matrix',
            'deploy' => 'Install commands',
            'compare' => 'CLI or Console table',
        ],
        'cli' => [
            'install' => 'Install commands',
            'terminal' => 'Workflow demo',
            'flags' => 'Command reference',
            'together' => 'CLI and Console together',
            'architecture' => 'Architecture diagram',
            'paths' => 'Files and exit codes',
        ],
    ];

    /** Icons available in <x-glyph>, for the admin icon pickers. */
    public const ICONS = [
        'activity', 'alert-triangle', 'bell', 'book-open', 'calendar', 'check', 'clock', 'cloud', 'copy', 'cpu',
        'database', 'download', 'eye', 'file-lock', 'git-branch', 'github', 'globe', 'headphones', 'key-round',
        'layers', 'layout-dashboard', 'lock', 'mail', 'mail-check', 'message-circle', 'message-square', 'monitor',
        'network', 'refresh', 'server', 'shield', 'shield-check', 'terminal', 'user-round', 'workflow',
    ];

    public static function settingKey(string $product): string
    {
        return "showcase_{$product}";
    }

    /** Saved content merged over the defaults, ready for the page. */
    public static function get(string $product): array
    {
        $defaults = self::defaults($product);
        $saved = Setting::get(self::settingKey($product));
        $data = $saved ? array_replace($defaults, $saved) : $defaults;
        $data['sections'] = ($saved['sections'] ?? []) + array_fill_keys(array_keys(self::SECTIONS[$product]), true);

        return self::withBuiltInImages($data, $defaults);
    }

    /**
     * The built-in screenshots (public/screenshots/...) fill every image an admin has not uploaded:
     * the hero, features by their small heading, gallery items by their title. Gallery items with
     * neither are left out.
     */
    private static function withBuiltInImages(array $data, array $defaults): array
    {
        if (blank($data['hero']['image'] ?? null)) {
            $data['hero']['image'] = $defaults['hero']['image'] ?? null;
        }
        $featureImages = collect($defaults['features'] ?? [])->mapWithKeys(fn ($f) => [$f['eyebrow'] => $f])->all();
        foreach ($data['features'] as &$f) {
            if (blank($f['image'] ?? null) && isset($featureImages[$f['eyebrow'] ?? ''])) {
                $f['image'] = $featureImages[$f['eyebrow']]['image'];
                $f['caption'] = filled($f['caption'] ?? null) ? $f['caption'] : $featureImages[$f['eyebrow']]['caption'];
            }
        }
        unset($f);
        $galleryImages = collect($defaults['gallery'] ?? [])->mapWithKeys(fn ($g) => [$g['title'] => $g['image']])->all();
        $data['gallery'] = array_values(array_filter(array_map(function ($g) use ($galleryImages) {
            $g['image'] = filled($g['image'] ?? null) ? $g['image'] : ($galleryImages[$g['title'] ?? ''] ?? null);

            return $g;
        }, $data['gallery'] ?? []), fn ($g) => filled($g['image'])));

        return $data;
    }

    /**
     * Content for the Admin → Product pages form: built-in screenshots show as empty upload fields
     * (they are not uploads). Leaving a field empty keeps the built-in screenshot.
     */
    public static function forEditor(string $product): array
    {
        $data = self::get($product);
        $strip = fn ($path) => is_string($path) && str_starts_with($path, '/') ? null : $path;
        $data['hero']['image'] = $strip($data['hero']['image'] ?? null);
        foreach (['features', 'gallery'] as $list) {
            foreach ($data[$list] as &$item) {
                $item['image'] = $strip($item['image'] ?? null);
            }
            unset($item);
        }

        return $data;
    }

    public static function save(string $product, array $data): void
    {
        $data = self::normalize($data);
        $old = self::images(self::get($product));
        Setting::put(self::settingKey($product), $data);

        // Delete uploads that are no longer used.
        foreach (array_diff($old, self::images($data)) as $path) {
            if (str_starts_with($path, self::IMAGE_DIR.'/')) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public static function reset(string $product): void
    {
        self::save($product, self::defaults($product));
    }

    /** Uploads are on the public disk; built-in defaults start with "/" (files in public/). */
    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, '/') ? asset(ltrim($path, '/')) : asset('storage/'.ltrim($path, '/'));
    }

    /** Repeater state is keyed by item ids; store plain lists. */
    private static function normalize(array $data): array
    {
        $data['hero']['ticks'] = array_values(array_filter($data['hero']['ticks'] ?? [], 'filled'));
        foreach (['features', 'extras', 'gallery'] as $list) {
            $data[$list] = array_values($data[$list] ?? []);
        }
        foreach ($data['features'] as &$f) {
            $f['bullets'] = array_values(array_filter($f['bullets'] ?? [], 'filled'));
            $f['visible'] = (bool) ($f['visible'] ?? true);
        }
        unset($f);

        return $data;
    }

    /** @return list<string> every image path used by one product's content */
    private static function images(array $data): array
    {
        $paths = [$data['hero']['image'] ?? null];
        foreach ([...($data['features'] ?? []), ...($data['gallery'] ?? [])] as $item) {
            $paths[] = $item['image'] ?? null;
        }

        return array_values(array_filter($paths, 'is_string'));
    }

    public static function defaults(string $product): array
    {
        return $product === 'cli' ? self::cliDefaults() : self::consoleDefaults();
    }

    private static function list(array $lines): array
    {
        return $lines;
    }

    private static function feature(string $icon, string $eyebrow, string $title, string $text, array $bullets, string $path, string $hint, ?string $image = null): array
    {
        return [
            'icon' => $icon, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text,
            'bullets' => self::list($bullets), 'image' => $image, 'caption' => null,
            'capture_path' => $path, 'capture_hint' => $hint, 'visible' => true,
        ];
    }

    private static function consoleDefaults(): array
    {
        $data = [
            'hero' => [
                'badge' => 'Self-hosted Management Console',
                'title' => 'Every server config, every change, one console you host.',
                'subtitle' => 'Back up and version the configuration of every Linux server, let AI flag risky settings, and give each team its own workspace. It runs on your infrastructure, so your configs never leave it.',
                'primary_label' => 'Install free',
                'primary_url' => '/dashboard/install/console',
                'secondary_label' => 'See the features',
                'secondary_url' => '#features',
                'ticks' => self::list(['One-command install', 'Linux and Windows', 'Free plan, no card', 'Your data stays on your servers']),
                'image' => null,
                'capture_path' => '/admin/dashboard',
                'capture_hint' => 'Full dashboard as super admin: KPI cards, vulnerability counts and the 7-day performance chart.',
            ],
            'features_heading' => [
                'eyebrow' => 'Features',
                'title' => 'Built for the people who run the servers',
                'sub' => 'From one Raspberry Pi to hundreds of production hosts: what the Management Console does for your team.',
            ],
            'features' => [
                self::feature('layout-dashboard', 'Dashboard', 'Your whole fleet on one screen',
                    'Users, systems, services and stored configuration files at a glance, with errors and warnings found this week and how they changed since last week.',
                    ['Live counts per workspace, or across all workspaces', '7-day performance chart: errors, warnings and clean configs', 'One-click jumps to users, workspaces and site settings'],
                    '/admin/dashboard', 'Scroll so the KPI cards and the Performance (Last 7 Days) chart are both visible.'),
                self::feature('server', 'Systems', 'Every registered server, searchable',
                    'Each Linux host running the AtGlance CLI registers itself. See its services, backups and open issues, and find any host in seconds.',
                    ['Search by name, IP, OS, tags or file hash', 'Services, backups and issue counts per system', 'Lock system info so hosts cannot overwrite it'],
                    '/systems-registered', 'Systems Registered list with the Search & Filter bar and 4+ systems.'),
                self::feature('git-branch', 'Configuration backups', 'Every change to every config file, versioned',
                    'nginx, sshd, systemd units and more are uploaded on each change. Browse the history of any file, compare by hash, view or download any version.',
                    ['Per server and per service version history', 'SHA-256 hash for each version, copy in one click', 'View in the browser or download the exact file'],
                    '/configuration-backups', 'Open one service\'s versions page showing several versions with hashes.'),
                self::feature('shield-check', 'AI configuration review', 'AI finds risky settings before attackers do',
                    'Each uploaded config can be reviewed by the AI provider you choose. Findings are rated by severity and listed per file, system and version.',
                    ['Vulnerabilities page with severity, summary and version', 'OpenAI, Anthropic, Gemini, Azure OpenAI, Mistral, Groq, DeepSeek, OpenRouter', 'Or keep it private with Ollama or LM Studio on your network'],
                    '/vulnerabilities-identified', 'Vulnerabilities Identified table with a mix of Error and Warning rows, or one config file with its AI validation open.'),
                self::feature('cpu', 'MCP server', 'Ask your AI assistant about your servers',
                    'A read-only MCP server ships with every install. Connect Claude Desktop, Claude Code or any MCP client and ask about systems, backups, reviews and vulnerabilities.',
                    ['Read-only by design, scoped to the user\'s workspaces', 'Copy-paste setup for popular AI clients', 'Runs behind the same gateway as the CLI API'],
                    '/connect-ai', 'MCP: connect AI tools page with the client setup snippets, or a Claude chat using AtGlance tools.'),
                self::feature('activity', 'Agent Monitoring', 'Live service health from every server',
                    'Servers running the AtGlance agent keep one outbound, encrypted connection to the console and report their services as they change. No inbound port is opened on the server.',
                    ['Live service monitoring page per system', 'Linux, Windows Server and macOS agents', 'One switch turns monitoring and heartbeats on or off'],
                    '/live-service-monitoring', 'Live Service Monitoring with two or three systems reporting.'),
                self::feature('network', 'Remote Tunnel', 'Reach servers on private networks',
                    'Connect sites behind NAT or firewalls through a connector, a relay, site-to-site IPsec or point-to-site access, and hop between sites. A premium feature.',
                    ['Sites with a connector or published gateway', 'Site-to-site IPsec and point-to-site access', 'Relay and multi-site hops'],
                    '/admin/settings?tab=plugins', 'Remote Tunnel Sites list with one connected site.'),
                self::feature('key-round', 'Service accounts', 'Machine keys for automation',
                    'Give scheduled CLI runs their own service account key (atgsa-) with only the scopes they need, instead of a person\'s key. Keys that expire soon trigger a notice.',
                    ['Scopes such as systems.register, configs.write, configs.read', 'Expiry notices at 15, 7, 3, 2, 1 and 0 days', 'Rotate a key without registering the server again'],
                    '/service-accounts', 'Service accounts list with one account open showing its scopes.'),
                self::feature('layers', 'Workspaces and roles', 'Separate teams, clear permissions',
                    'Group servers into workspaces for each team or environment. Workspace admins manage members, tags, AI reviews, backups and notifications for their own workspace.',
                    ['Super admin, admin and user roles', 'Fine-grained access for workspace admins', 'Activity log for every workspace'],
                    '/admin/workspaces/{id}', 'A workspace settings page with the tab bar (General, Members, Tags, AI, Backups, Notifications, Activity) and the Members table.'),
                self::feature('bell', 'Notifications', 'The right people hear about changes',
                    'Send events such as a new host, a changed config or a new vulnerability to the channel each team already uses.',
                    ['Email, Microsoft Teams, Slack, Telegram, n8n, webhooks and SMS', 'Groups per workspace, plus organization-wide groups', 'Each member picks the events they want'],
                    '/admin/notifications', 'Notifications page with two or three groups on different channels.'),
                self::feature('database', 'Backup and restore', 'Scheduled backups, one-click restore',
                    'Back up the console database and all stored configuration files on a schedule, and restore them when you need to. Backups carry a manifest and checksums and can be encrypted.',
                    ['Database and configuration backups, separately', 'Per-workspace backup schedules with notes', 'Local disk, S3, NFS, Azure Storage or your own Google Drive'],
                    '/admin/settings/backups', 'Backup & Restore tab with the schedule and the list of past backups.'),
                self::feature('key-round', 'Single sign-on', 'Sign in with the identity provider you already use',
                    'Turn on the SSO plugin and add one or more providers. Users sign in with their company account instead of a separate password.',
                    ['Google, Microsoft Entra ID, GitHub, GitLab and Okta', 'Domain and tenant restrictions', 'Email and password still available as a fallback'],
                    '/admin/settings?tab=sso', 'SSO Configuration tab with one provider open. Hide client secrets before capturing.'),
                self::feature('globe', 'Your domain, your brand', 'Custom domain, HTTPS and white-label',
                    'Serve the console on your own domain with HTTPS, and show your organization\'s name, logo and help pages to your users.',
                    ['Custom domain with built-in CA or your own certificate', 'Organization name, logo and favicon', 'About, Features, FAQ, Support and Contact pages'],
                    '/admin/settings?tab=site', 'Site Configuration tab with the Access URL / HTTPS section, or the Plugins tab.'),
            ],
            'extras_heading' => ['eyebrow' => 'And more', 'title' => 'Production-ready from day one'],
            'extras' => [
                ['icon' => 'workflow', 'title' => 'Survives database outages', 'text' => 'If MySQL goes down, writes are queued in Redis and replayed when it comes back.'],
                ['icon' => 'cloud', 'title' => 'Runs anywhere', 'text' => 'One command on a Linux VM or Windows, or as containers on ECS, Kubernetes or Cloud Run.'],
                ['icon' => 'refresh', 'title' => 'Safe updates', 'text' => 'The updater backs up first, checks health after, and tells you how to roll back.'],
                ['icon' => 'lock', 'title' => 'Inside your boundary', 'text' => 'Configs and AI reviews stay on your infrastructure. Nothing is sent to AtGlance.'],
                ['icon' => 'terminal', 'title' => 'API gateway for the CLI', 'text' => 'The CLI talks to a dedicated gateway on port 8002 with personal access tokens.'],
                ['icon' => 'activity', 'title' => 'Activity history', 'text' => 'Every sign-in, change and upload is recorded per user and per workspace.'],
            ],
            'gallery' => [],
        ];

        return self::withConsoleScreenshots($data);
    }

    /**
     * Real screenshots of the console (public/screenshots/console, see its README.md), with a
     * caption under each. Captured from a running console; e-mail addresses and LAN addresses
     * were replaced with example values before capture.
     */
    private static function withConsoleScreenshots(array $data): array
    {
        $shot = fn (string $name) => "/screenshots/console/console-{$name}.jpg";

        // Shown in the browser bar above each screenshot.
        $paths = [
            'dashboard' => '/admin/dashboard', 'systems' => '/systems-registered', 'config-backups' => '/configuration-backups',
            'config-file' => '/configuration-backups/2257/view', 'mcp' => '/connect-ai', 'servers' => '/servers',
            'remote-tunnel' => '/admin/tunnel', 'service-accounts' => '/admin/service-accounts', 'enterprise-console' => '/entrpirse_console',
            'notifications' => '/admin/notifications', 'settings-backups' => '/admin/settings?tab=backup', 'settings-plugins' => '/admin/settings?tab=plugins',
            'settings-info' => '/admin/settings',
        ];
        $data['hero']['image'] = $shot('dashboard');
        $data['hero']['capture_path'] = '/admin/dashboard';

        // Feature (by its eyebrow) => [screenshot, caption].
        $byFeature = [
            'Dashboard' => ['dashboard', 'Users, systems, services and config files at a glance, with shortcuts to every admin area and live server status.'],
            'Systems' => ['systems', 'Every registered server with its status, OS, address, workspace, service and backup counts, searchable by name, IP, tags or system key.'],
            'Configuration backups' => ['config-backups', 'All backed-up services, filtered by application or system type, with the AI check result and the version count of each.'],
            'AI configuration review' => ['config-file', 'One stored version: the exact unit file, its details and the history of AI validation runs for it.'],
            'MCP server' => ['mcp', 'Step-by-step setup for Claude Code, Claude Desktop, VS Code, GitHub Copilot, n8n and other MCP clients.'],
            'Agent Monitoring' => ['servers', 'Live view from the agents: online, degraded, delayed and offline servers, the heartbeat timeline, availability and uptime by server.'],
            'Remote Tunnel' => ['remote-tunnel', 'Sites, connection profiles, connectors, sessions, policies, point-to-site VPN, published gateway, relays and the internal CA.'],
            'Service accounts' => ['service-accounts', 'Machine identities for the CLI, scoped to the organization or one workspace, so a server keeps working when its owner leaves.'],
            'Workspaces and roles' => ['enterprise-console', 'Enterprise Console: every workspace with its admins, members, systems and last backup, plus organization-wide settings.'],
            'Notifications' => ['notifications', 'Notification groups per organization or workspace: one destination each (email list, Slack or Teams channel, webhook).'],
            'Backup and restore' => ['settings-backups', 'Separate database and config-file backups, each with its own schedule and destinations (this server, S3, NFS, Azure or Google Drive), encrypted with AES-256-GCM.'],
            'Single sign-on' => ['settings-plugins', 'Plugins: turn on Custom Domain & HTTPS, SSO, AI Connect, S3, MCP, Agent Monitoring, Azure Storage and Google Drive.'],
            'Your domain, your brand' => ['settings-info', 'Site settings: domain, organization name, storage URL, access URL, server IP and HTTPS status.'],
        ];
        foreach ($data['features'] as &$feature) {
            if (isset($byFeature[$feature['eyebrow']])) {
                [$name, $caption] = $byFeature[$feature['eyebrow']];
                $feature['image'] = $shot($name);
                $feature['caption'] = $caption;
                $feature['capture_path'] = $paths[$name] ?? $feature['capture_path'];
            }
        }
        unset($feature);

        $data['gallery'] = [
            ['image' => $shot('service-versions'), 'title' => 'Every version of a service', 'caption' => 'Service type, version count, file, system, upload time and hash, with view and download for each version.'],
            ['image' => $shot('users'), 'title' => 'Users and roles', 'caption' => 'Administrators, workspace admins and users, with their systems, services, configs and last sign-in.'],
            ['image' => $shot('settings-agents'), 'title' => 'Agent settings', 'caption' => 'Bidirectional or report-only agents, heartbeat and offline timings, and service heartbeat rules for every server.'],
            ['image' => $shot('workspaces'), 'title' => 'Workspaces', 'caption' => 'Create a workspace and become its admin, then add users and admins to it.'],
            ['image' => $shot('profile'), 'title' => 'Your profile', 'caption' => 'Your role, systems, configuration files, latest backup and active API keys.'],
        ];

        return $data;
    }

    private static function cliDefaults(): array
    {
        $shot = fn (string $name) => "/screenshots/cli/cli-{$name}.svg";

        return [
            'hero' => [
                'badge' => 'AtGlance CLI for Linux servers',
                'title' => 'Server health and config backups, from one command.',
                'subtitle' => 'One small binary for every systemd host. See each service, its ports and log errors on one screen, back up its unit files to your Management Console, and restore any version safely.',
                'primary_label' => 'Download the CLI',
                'primary_url' => '/downloads#cli',
                'secondary_label' => 'Command reference',
                'secondary_url' => '#commands',
                'ticks' => self::list(['.deb, .rpm and .tar.gz', 'One static binary, about 3 MB', 'No daemon, no open ports', 'Agent for Windows and macOS in 3.0']),
                'image' => $shot('status'),
                'capture_path' => 'atglance status',
                'capture_hint' => 'Terminal running atglance status.',
            ],
            'features_heading' => [
                'eyebrow' => 'Features',
                'title' => 'Everything about a server, in one terminal',
                'sub' => 'Health commands work on their own. Connect the console to keep every version of every unit file.',
            ],
            'features' => [
                self::feature('monitor', 'Health', 'Every service on one screen',
                    'atglance status lists running and failed services with state, uptime, listening ports and health. Application services come first, then the operating system\'s own.',
                    ['--all adds stopped services; --type application or system shows one kind', 'Health: Working, Starting, Restarting, Done, Stopped, Failed, Error, Warning', 'Exit code 3 when a service is Failed or Restarting, for monitoring checks'],
                    'atglance status', 'Terminal running atglance status.', $shot('status-sudo')),
                self::feature('eye', 'Service detail', 'Inspect one service in depth',
                    'atglance service nginx shows the unit file, main PID, restarts, ports and how many errors and warnings it logged in the last 24 hours.',
                    ['Works with ssh or ssh.service', 'JSON with -o json', 'watch refreshes the overview every 5 seconds'],
                    'atglance service ssh', 'Terminal with the detail view of one service.', $shot('service')),
                self::feature('server', 'Registration', 'Connect a server in two commands',
                    'Log in with your personal API key, then register. The server makes its own system key and keeps it readable only by root. Keys are never accepted as arguments.',
                    ['login, whoami and logout', 'system register, status, deregister, reactivate, rotate-key', 'A service account key for scheduled runs'],
                    'sudo atglance system register', 'Terminal showing a successful system register.', $shot('register')),
                self::feature('git-branch', 'Config backups', 'Every change to every unit file, versioned',
                    'sync backs up every service whose unit file changed and skips the rest. List versions, compare one with the live file, and restore it with an automatic rollback.',
                    ['config backup, list, pull, diff and restore', 'Offline queue: backups wait until the console is back', 'restore refuses the SSH service over SSH, restarts and rolls back on failure'],
                    'sudo atglance sync', 'Terminal showing sync with uploaded and unchanged services.', $shot('sync')),
                self::feature('shield-check', 'Health check', 'One command checks the whole setup',
                    'check tests settings, the console, the token, the clock, the system, systemd, the journal, the offline queue, both keys\' expiry and the schedule.',
                    ['ok, warn or fail per line', 'Warns when a key has 15 days or fewer left', 'Alias: doctor'],
                    'sudo atglance check', 'Terminal showing check with ok lines.', $shot('check')),
                self::feature('calendar', 'Daily schedule', 'Check and sync every day, by itself',
                    'schedule enable writes a cron job: a daily check around 06:00 and a daily sync at night, at random minutes so servers do not all call the console at once.',
                    ['Runs with the service account key, never your personal key', 'Output in the system log: journalctl -t atglance', 'schedule status shows the times and last log lines'],
                    'sudo atglance schedule enable', 'Terminal showing schedule enable.', $shot('schedule-enable')),
                self::feature('lock', 'Security log', 'See who forced what, from the terminal',
                    'Organisation admins read the security log: failed, successful and locked force actions, newest first, and the accounts that are locked.',
                    ['security events with --type, --since, --until, --user', 'security lockouts', 'Table or JSON output'],
                    'sudo atglance security events', 'Terminal showing security events.', $shot('security-events')),
            ],
            'extras_heading' => ['eyebrow' => 'And more', 'title' => 'Small, predictable, scriptable'],
            'extras' => [
                ['icon' => 'download', 'title' => 'Packages for every distro', 'text' => '.deb for amd64, arm64 and armhf; .rpm for x86_64 and aarch64; .tar.gz for any systemd Linux.'],
                ['icon' => 'cpu', 'title' => 'Fast on a Raspberry Pi', 'text' => 'status takes 0.14 s of CPU and under 8 MB of memory on an armv7 board, against 3.45 s for 1.x.'],
                ['icon' => 'copy', 'title' => 'JSON for scripts', 'text' => 'export prints every service as JSON, with an optional log summary.'],
                ['icon' => 'refresh', 'title' => 'Moves from 1.x by itself', 'text' => 'Install over 1.x: settings are imported, the system keeps its id and key, and the old flags still work.'],
            ],
            'gallery' => [],
        ];
    }
}
