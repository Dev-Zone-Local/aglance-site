<x-layouts.site title="Remote Tunnel" description="Connect the AtGlance Management Console with servers on separate private networks, even without a public URL: an outbound Agent Tunnel with mutual TLS, a published gateway, Site-to-Site IPsec, Direct routes and bastion or proxy hops, with every session audited.">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Premium feature" title="Remote Tunnel" large>
            Connect your Management Console with servers and applications on separate private networks, such as a data centre with 10 servers and a branch with 50. It works even when the console has only a private address and no public URL. No inbound firewall ports are needed where avoidable, and every session is authorised and audited.
        </x-page-header>

        <section class="mt-14">
            <x-section-heading eyebrow="Ways to connect" title="Pick the one your network already allows." />
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                <x-feature icon="network" title="Agent Tunnel (default)">A small connector inside the remote network dials <b>out</b> to the console over TLS 443 with mutual TLS. Sessions come back through that connection. No inbound rules, works through HTTP proxies.</x-feature>
                <x-feature icon="shield-check" title="Site-to-Site IPsec VPN">An IKEv2, route-based tunnel between the console's strongSwan gateway and the remote firewall. Certificates by default, modern ciphers only, a ready-made config sheet for the firewall team.</x-feature>
                <x-feature icon="globe" title="Direct">The networks are already linked (MPLS, ExpressRoute, Direct Connect, peering, your own VPN). No tunnel; the console reaches the hosts as they are.</x-feature>
                <x-feature icon="server" title="Published gateway">The console stays private. Publish only its tunnel service on one HTTPS port (firewall or DMZ, TCP passthrough); every site's connectors dial that address.</x-feature>
                <x-feature icon="workflow" title="Bastion and proxy hops">Reuse the jump hosts and proxies you already have: SSH bastions (ProxyJump), SOCKS5 or HTTP CONNECT, chained after any method.</x-feature>
                <x-feature icon="cloud" title="Relay">When neither side can accept connections: everything dials out to a relay on a public VM. Mutual TLS end to end; the relay forwards ciphertext only.</x-feature>
                <x-feature icon="network" title="Subnet router and policies">Route whole subnets (TCP, UDP, ICMP) for monitoring and discovery, under a default-deny policy matrix, with NAT for sites that use the same private range.</x-feature>
                <x-feature icon="layers" title="Sites and high availability">Group each remote network as a Site with its subnets. Run two connectors per Site; if one stops, the other takes over. Servers in the Site can reach the console through the connector.</x-feature>
            </div>
        </section>

        <section class="mt-16">
            <x-section-heading eyebrow="How the Agent Tunnel works" title="Outbound only." />
            <x-code-block title="diagram" class="mt-6">Remote site (inbound blocked)                        Management Console
[Server A :22]  [Server B :443]
        ▲ local connection
[Connector] ──── outbound TLS 443, mutual TLS, one connection ────► [Tunnel service]
                                                                          ▲
                                                Admin: "Open terminal to Server A"</x-code-block>
        </section>

        <section class="mt-16">
            <x-section-heading eyebrow="Security" title="Built to be audited." />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-feature icon="key-round" title="Mutual TLS, short-lived certificates">Each connector creates its own key; the console's internal CA signs a certificate for 1 to 7 days, renewed automatically and revocable at once.</x-feature>
                <x-feature icon="lock" title="One-time enrollment">An enrollment token works once, for an hour, and carries the console CA's fingerprint, so a connector never trusts the wrong console.</x-feature>
                <x-feature icon="check" title="Two allow-lists">A target must be in the profile's allow-list on the console and in the connector's own list on the host. The console is never an open proxy.</x-feature>
                <x-feature icon="shield" title="IKEv2 and modern crypto">IPsec profiles accept only IKEv2 with AES-GCM, SHA-256 or better and DH groups 14, 19, 20 or 21, with PFS.</x-feature>
                <x-feature icon="activity" title="Per-session audit">Who opened what, to which host and port, when, for how long and how many bytes — in the console's activity log.</x-feature>
                <x-feature icon="user-round" title="RBAC and workspaces">Organization and workspace profiles, separate permissions to manage profiles, open sessions, end others' sessions and reveal credentials.</x-feature>
            </div>
        </section>

        <section class="mt-16 rounded-card bg-ag-surface p-8 text-center">
            <h2 class="text-xl font-semibold text-ag-ink">Included in Premium Features</h2>
            <p class="mt-2 text-sm text-ag-subtle">Or added on its own to a custom licence. Needs Management Console 3.0.0 or later.</p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <x-button href="{{ route('pricing') }}">See pricing</x-button>
                <x-button href="{{ route('docs.show', 'remote-tunnel') }}" variant="ghost">Read the docs</x-button>
            </div>
        </section>
    </div>
</x-layouts.site>
