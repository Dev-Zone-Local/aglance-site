<?php

namespace App\Support;

/**
 * What can be installed where, and the guided steps for each product + target.
 * Add a target here (and its instructions in the admin panel, Downloads page) to make it
 * appear in the dashboard installer. Status "soon" shows a "Coming soon" card.
 */
class InstallCatalog
{
    public const PRODUCTS = [
        'cli' => [
            'name' => 'AtGlance CLI',
            'tagline' => 'Agent for your Linux / systemd hosts',
            'icon' => 'cpu',
            'targets' => [
                'linux' => ['name' => 'Linux server', 'hint' => 'Any systemd Linux: .deb, .rpm or .tar.gz', 'icon' => 'terminal', 'status' => 'ready'],
                'windows' => ['name' => 'Windows Server', 'hint' => 'The AtGlance agent as a Windows service', 'icon' => 'monitor', 'status' => 'ready'],
                'macos' => ['name' => 'macOS', 'hint' => 'The AtGlance agent as a launchd job', 'icon' => 'cpu', 'status' => 'ready'],
                'aws-vm' => ['name' => 'AWS EC2 extension', 'hint' => 'Self-deploy on cloud VMs', 'icon' => 'cloud', 'status' => 'soon'],
                'azure-vm' => ['name' => 'Azure VM extension', 'hint' => 'Self-deploy on cloud VMs', 'icon' => 'cloud', 'status' => 'soon'],
            ],
        ],
        'console' => [
            'name' => 'Management Console',
            'tagline' => 'Self-hosted console for your organization',
            'icon' => 'server',
            'targets' => [
                'linux' => ['name' => 'Linux (Docker)', 'hint' => 'Any Docker host', 'icon' => 'terminal', 'status' => 'ready'],
                'windows' => ['name' => 'Windows (Docker)', 'hint' => 'Docker Desktop / Windows Server', 'icon' => 'monitor', 'status' => 'ready'],
                'aws' => ['name' => 'AWS', 'hint' => 'One-click deploy to your account', 'icon' => 'cloud', 'status' => 'soon'],
                'azure' => ['name' => 'Azure', 'hint' => 'One-click deploy to your subscription', 'icon' => 'cloud', 'status' => 'soon'],
            ],
        ],
    ];

    public static function product(?string $id): ?array
    {
        return $id !== null ? (self::PRODUCTS[$id] ?? null) : null;
    }

    /**
     * Steps for a product + target, from the Downloads settings.
     * mode "install" = new installation, "update" = update an existing one.
     *
     * @return array<int, array{key: string, title: string, text?: ?string, markdown?: ?string, code?: ?string, checksum?: ?string, action?: ?array, link?: ?array}>
     */
    public static function steps(string $productId, string $targetId, string $mode = 'install'): array
    {
        $product = Downloads::resolved()[$productId] ?? null;
        if (! $product) {
            return [];
        }

        $latest = $product['releases'][0] ?? null;
        $os = isset(Downloads::platformsFor($productId)[$targetId]) ? $targetId : 'linux';
        $p = $product['platforms'][$os] ?? [];
        $what = self::PRODUCTS[$productId]['name'];
        $label = Downloads::PLATFORMS[$os] ?? 'Linux';
        $steps = [];

        if ($mode === 'update') {
            $steps[] = [
                'key' => 'update-version',
                'title' => 'Check the latest version',
                'text' => $latest
                    ? "The latest {$what} is v{$latest['version']}.".(! empty($latest['summary']) ? ' '.$latest['summary'] : '')
                    : "No {$what} release is published yet.",
                'link' => $latest ? [
                    'href' => Downloads::releaseUrl($productId, $latest['version']),
                    'label' => 'Read the release notes',
                ] : null,
            ];
            $steps[] = [
                'key' => 'update-script',
                'title' => 'Download the update script',
                'text' => "Get the {$label} update script.",
                'action' => ! empty($p['script_url']) ? ['href' => $p['script_url'], 'label' => 'Download update script'] : null,
            ];
            if (! empty($p['update_steps'])) {
                $steps[] = ['key' => 'update-run', 'title' => 'Run the update', 'markdown' => $p['update_steps']];
            }

            return $steps;
        }

        $file = $p['file_name'] ?? null;
        $steps[] = [
            'key' => 'download',
            'title' => 'Download',
            'text' => $latest
                ? "Get {$what} v{$latest['version']}".($file ? " ({$file})" : '')." for {$label}."
                : "Get the {$what} for {$label}.",
            'action' => ! empty($p['url']) ? ['href' => $p['url'], 'label' => 'Download '.($file ?: 'installer')] : null,
        ];

        // Checksum of the file for this platform (uploaded file), else the release checksum.
        $sum = $p['sha256'] ?? ($os === 'linux' ? ($latest['checksum'] ?? null) : null);
        if (Downloads::hasChecksum($productId) && $latest && $sum) {
            $forFile = ['file_name' => $file ?: ($product['file_name'] ?? null)];
            $release = [...$latest, 'checksum' => $sum];
            $steps[] = [
                'key' => 'verify',
                'title' => 'Verify the download',
                'text' => $os === 'windows'
                    ? 'Check the SHA-256 checksum in PowerShell. It prints "True" when the file is genuine.'
                    : 'Check the SHA-256 checksum. It prints "OK" when the file is genuine.',
                'checksum' => $sum,
                'code' => match ($os) {
                    'windows' => Downloads::validateCommandWindows($forFile, $release),
                    'macos' => Downloads::validateCommandMac($forFile, $release),
                    default => Downloads::validateCommand($forFile, $release),
                },
            ];
        }

        if (! empty($p['install_steps'])) {
            $steps[] = ['key' => 'install', 'title' => 'Install', 'markdown' => $p['install_steps']];
        }

        $steps[] = $productId === 'cli'
            ? ['key' => 'licence', 'title' => 'Register your host', 'text' => 'Configure the CLI and register this host with your Management Console.', 'link' => ['href' => route('docs.show', 'quickstart'), 'label' => 'Read the quickstart']]
            : ['key' => 'licence', 'title' => 'Activate with a licence', 'text' => 'The installer asks for a licence key. Create one, enter the emailed code, and paste the key into the installer.', 'link' => ['href' => route('dashboard.licences'), 'label' => 'Manage licences']];

        return $steps;
    }
}
