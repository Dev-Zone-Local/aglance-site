<?php

// Remote Tunnel connector downloads (premium, remote_tunnel), shown on
// /dashboard/downloads/remote-tunnel-connector to users whose licence includes Remote Tunnel.
// Files are served from CONNECTOR_DOWNLOAD_BASE_URL/<version>/<file>, only through the
// entitlement-checked download route.
return [
    'base_url' => rtrim((string) env('CONNECTOR_DOWNLOAD_BASE_URL', 'https://downloads.atglance.live/connector'), '/'),
    'image' => 'atglance/connector',
    'gateway_image' => 'atglance/ce-atglance-tunnel',
    'releases' => [
        [
            'version' => '1.0.0',
            'released' => '2026-10-31',
            'notes' => "First release: enrollment with a one-time token (the console CA is pinned by fingerprint), mutual TLS over a single outbound connection (WebSocket, yamux), HTTPS_PROXY support, reconnect with backoff, certificate renewal, a local allow-list.",
            'min_console' => '3.0.0',
            'files' => [
                'linux-amd64' => ['file' => 'atglance-connector-linux-amd64', 'sha256' => null],
                'linux-arm64' => ['file' => 'atglance-connector-linux-arm64', 'sha256' => null],
                'systemd' => ['file' => 'atglance-connector.service', 'sha256' => null],
            ],
        ],
    ],
    // Which connector works with which console.
    'compatibility' => [
        ['connector' => '1.0.x', 'console' => '3.0.0 and later'],
    ],
];
