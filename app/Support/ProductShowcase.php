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
        ],
        'cli' => [
            'terminal' => 'Terminal demo',
            'flags' => 'Command table',
            'architecture' => 'Architecture diagram',
            'lifecycle' => 'Register / deregister flow',
            'paths' => 'Where state lives',
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
        $saved = Setting::get(self::settingKey($product));
        $data = $saved ? array_replace(self::defaults($product), $saved) : self::defaults($product);
        $data['sections'] = ($saved['sections'] ?? []) + array_fill_keys(array_keys(self::SECTIONS[$product]), true);

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

    public static function imageUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.ltrim($path, '/')) : null;
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

    private static function feature(string $icon, string $eyebrow, string $title, string $text, array $bullets, string $path, string $hint): array
    {
        return [
            'icon' => $icon, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text,
            'bullets' => self::list($bullets), 'image' => null, 'caption' => null,
            'capture_path' => $path, 'capture_hint' => $hint, 'visible' => true,
        ];
    }

    private static function consoleDefaults(): array
    {
        return [
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
                self::feature('layers', 'Workspaces and roles', 'Separate teams, clear permissions',
                    'Group servers into workspaces for each team or environment. Workspace admins manage members, tags, AI reviews, backups and notifications for their own workspace.',
                    ['Super admin, admin and user roles', 'Fine-grained access for workspace admins', 'Activity log for every workspace'],
                    '/admin/workspaces/{id}', 'A workspace settings page with the tab bar (General, Members, Tags, AI, Backups, Notifications, Activity) and the Members table.'),
                self::feature('bell', 'Notifications', 'The right people hear about changes',
                    'Send events such as a new host, a changed config or a new vulnerability to the channel each team already uses.',
                    ['Email, Microsoft Teams, Slack, Telegram, n8n, webhooks and SMS', 'Groups per workspace, plus organization-wide groups', 'Each member picks the events they want'],
                    '/admin/notifications', 'Notifications page with two or three groups on different channels.'),
                self::feature('database', 'Backup and restore', 'Scheduled backups, one-click restore',
                    'Back up the console database and all stored configuration files on a schedule, locally or to S3, and restore them when you need to.',
                    ['Database and configuration backups, separately', 'Per-workspace backup schedules with notes', 'Local disk or S3, with one-click migration between them'],
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
    }

    private static function cliDefaults(): array
    {
        return [
            'hero' => [
                'badge' => 'AtGlance CLI for Linux servers',
                'title' => 'Server health and config backups, from one command.',
                'subtitle' => 'A small agent for systemd hosts. See every service, its ports and log signals on one screen, back up its configuration, and sync it with your Management Console.',
                'primary_label' => 'Install the CLI',
                'primary_url' => '/dashboard/install/cli',
                'secondary_label' => 'See the features',
                'secondary_url' => '#features',
                'ticks' => self::list(['Ubuntu and Debian (.deb)', 'No daemon required', 'Talks only to your console', 'Open command reference']),
                'image' => null,
                'capture_path' => 'atglance',
                'capture_hint' => 'Terminal running `atglance` with the full service overview table.',
            ],
            'features_heading' => [
                'eyebrow' => 'Features',
                'title' => 'Everything about a server, in one terminal',
                'sub' => 'Built for SREs who live in SSH sessions.',
            ],
            'features' => [
                self::feature('monitor', 'Overview', 'Every service on one screen',
                    'Run atglance and see every systemd service with its health, ports and recent log signals, classified the same way every time.',
                    ['Service discovery from systemd', 'Deterministic health classification', 'Port mapping and error/warning log signals'],
                    'atglance', 'Terminal with the overview table of all services.'),
                self::feature('eye', 'Drill-down', 'Inspect one service in depth',
                    'atglance --app nginx.service shows the unit, its status, ports, recent errors and its configuration files.',
                    ['Status, ports and recent log lines', 'Configuration files of the service', 'Works on any systemd unit'],
                    'atglance --app nginx.service', 'Terminal with the detail view of one service.'),
                self::feature('git-branch', 'Configuration backups', 'Back up and restore config files',
                    'Save a service\'s configuration to the Management Console, list stored versions, and import any version back onto the server.',
                    ['--config-save uploads a new version', '--config-show lists stored versions', '--config-import restores a version by ID'],
                    'sudo atglance --app nginx.service --config-save', 'Terminal showing config-save, then config-show with version IDs.'),
                self::feature('server', 'Registration', 'Register a server in seconds',
                    'Point the CLI at your console with a personal access token. The host gets a permanent validation hash and shows up in the console.',
                    ['--configure and --validate the token', '--system-register, deregister and reactivate', 'Permanent hash in /etc/environment'],
                    'atglance --system-register', 'Terminal showing a successful --system-register.'),
                self::feature('activity', 'Watch mode', 'Live refresh while you work',
                    'atglance watch keeps the overview on screen and refreshes it on an interval, so you see a service recover or fail as it happens.',
                    ['Interval you choose', 'Same view as the overview', 'Export a snapshot as JSON any time'],
                    'atglance watch --interval 5', 'Terminal in watch mode.'),
            ],
            'extras_heading' => ['eyebrow' => 'And more', 'title' => 'Small, predictable, scriptable'],
            'extras' => [
                ['icon' => 'download', 'title' => 'Debian package', 'text' => 'Install and update with a .deb; checksums are published for every release.'],
                ['icon' => 'file-lock', 'title' => 'Local versioned backups', 'text' => 'Config backups are also kept locally, numbered so nothing is overwritten.'],
                ['icon' => 'copy', 'title' => 'JSON export', 'text' => 'atglance export --format json for scripts and dashboards.'],
                ['icon' => 'lock', 'title' => 'Token auth', 'text' => 'Personal access tokens, validated against your console before any upload.'],
            ],
            'gallery' => [],
        ];
    }
}
