{{-- Downloads → Remote Tunnel Connector (premium remote_tunnel). Links go through the
     entitlement-checked route; without Remote Tunnel the page is locked. --}}
<x-layouts.app title="Remote Tunnel Connector">
    <x-page-header eyebrow="Downloads" title="Remote Tunnel Connector">
        A small service you run inside a remote network. It dials out to your Management Console over TLS and carries
        terminal and port-forwarding sessions back, with no inbound firewall change.
    </x-page-header>

    @if (! $source)
        <x-card class="mt-6" data-testid="connector-locked">
            <div class="flex items-start gap-3">
                <x-glyph name="lock" class="mt-0.5 shrink-0 text-ag-muted" />
                <div>
                    <p class="font-medium text-ag-ink">Remote Tunnel is not included in your licence.</p>
                    <p class="mt-1 text-sm text-ag-subtle">It is part of Premium Features, or can be added to a custom licence on its own.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button href="{{ route('features.remote-tunnel') }}" variant="ghost">What is Remote Tunnel?</x-button>
                        <x-button href="{{ route('contact') }}">Upgrade</x-button>
                    </div>
                </div>
            </div>
        </x-card>
    @else
        <p class="mt-2 text-sm text-ag-subtle" data-testid="connector-source">
            Included in your licence {{ $source === 'bundle' ? 'via Premium Features' : '(granted individually)' }}.
        </p>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <x-card>
                <h2 class="text-base font-semibold text-ag-ink">Docker</h2>
                <p class="mt-1 text-sm text-ag-subtle">Create an enrollment token on the console's profile page (Add connector), then:</p>
                <x-code-block class="mt-3">docker run -d --name atglance-connector --restart unless-stopped \
  -v atglance-connector:/var/lib/atglance-connector \
  -e ATGLANCE_CONSOLE_URL=wss://console.example.com:8443 \
  -e ATGLANCE_ENROLL_TOKEN=atgct-… \
  -e ATGLANCE_ALLOW='10.20.0.0/24:22,443' \
  {{ $image }}:{{ $releases[0]['version'] ?? 'latest' }}</x-code-block>
            </x-card>
            <x-card>
                <h2 class="text-base font-semibold text-ag-ink">Linux (systemd)</h2>
                <x-code-block class="mt-3">sudo install -m 0755 atglance-connector-linux-amd64 /usr/local/bin/atglance-connector
sudo install -m 0644 atglance-connector.service /etc/systemd/system/
sudo install -d -m 0700 /var/lib/atglance-connector /etc/atglance-connector
echo "10.20.0.0/24 22,443" | sudo tee /etc/atglance-connector/allow.conf
sudo atglance-connector enroll --console wss://console.example.com:8443 --token atgct-…
sudo systemctl enable --now atglance-connector</x-code-block>
            </x-card>
        </div>

        <h2 class="mt-10 text-lg font-semibold text-ag-ink">Releases</h2>
        @foreach ($releases as $release)
            <x-card class="mt-4" data-testid="connector-release">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-semibold text-ag-ink">v{{ $release['version'] }}</h3>
                    <span class="text-xs text-ag-muted">{{ $release['released'] }} · needs console {{ $release['min_console'] }}+</span>
                </div>
                <p class="mt-2 text-sm text-ag-subtle">{{ $release['notes'] }}</p>
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="text-left text-xs text-ag-muted"><th class="py-1">File</th><th class="py-1">SHA-256</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($release['files'] as $key => $entry)
                            <tr class="border-t border-ag-line">
                                <td class="py-2 font-mono text-xs">{{ $entry['file'] }}</td>
                                <td class="py-2 font-mono text-[11px] text-ag-subtle break-all">{{ $entry['sha256'] ?? 'published with the release' }}</td>
                                <td class="py-2 text-right"><a class="text-ag-teal-text underline" href="{{ route('dashboard.connector.file', [$release['version'], $key]) }}">Download</a></td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-ag-line"><td class="py-2 font-mono text-xs">{{ $image }}:{{ $release['version'] }}</td><td class="py-2 text-xs text-ag-subtle">Docker image (linux/amd64, linux/arm64)</td><td></td></tr>
                    </tbody>
                </table>
            </x-card>
        @endforeach

        <h2 class="mt-10 text-lg font-semibold text-ag-ink">Compatibility</h2>
        <x-card class="mt-4">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-ag-muted"><th class="py-1">Connector</th><th class="py-1">Management Console</th></tr></thead>
                <tbody>
                    @foreach ($compatibility as $row)
                        <tr class="border-t border-ag-line"><td class="py-2">{{ $row['connector'] }}</td><td class="py-2">{{ $row['console'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-3 text-xs text-ag-muted">The console's tunnel service is the {{ $gatewayImage }} image, pulled with the console (docker-compose.tunnel.yml). The Site-to-Site IPsec gateway image follows in a later release.</p>
        </x-card>
    @endif
</x-layouts.app>
