@php
    $commandFor = fn (array $release) => \App\Support\Downloads::validateCommand($product, $release);
@endphp

{{-- Own styles: Filament v4 does not compile Tailwind classes used in custom views. --}}
<style>
    .agdl { display: grid; gap: 12px; }
    .agdl-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .agdl-title { font-size: 14px; font-weight: 500; color: #14171b; }
    .agdl-muted { font-size: 12px; color: #8a9099; }
    .agdl-card { background: #f4f6f8; border-radius: 12px; padding: 14px 16px; }
    .agdl-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; }
    .agdl-ver { font-size: 14px; font-weight: 500; color: #14171b; }
    .agdl-badge { border-radius: 8px; padding: 2px 8px; font-size: 12px; font-weight: 500; background: #e6e9ee; color: #5b626b; }
    .agdl-badge.latest { background: #e3faf3; color: #1fa874; }
    .agdl-code { display: block; word-break: break-all; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; color: #262a30; }
    .agdl-cmd { display: block; word-break: break-all; margin-top: 4px; border-radius: 8px; background: #14171b; color: #e6e9ee; padding: 8px 12px; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; }
    .agdl-label { margin-top: 8px; font-size: 12px; color: #8a9099; }
    .agdl-notes { margin-top: 8px; font-size: 12px; color: #5b626b; }
    .agdl-actions { margin-left: auto; display: inline-flex; gap: 6px; }
    .agdl-badge.type { background: #e8f4f8; color: #0f7f96; }
    .agdl-badge.security { background: #fdecec; color: #c0392b; }
    .agdl-rtitle { font-size: 13px; font-weight: 500; color: #262a30; }
    .agdl-summary { margin-top: 4px; font-size: 13px; color: #3c4249; }
    .agdl-md { margin-top: 8px; font-size: 13px; color: #3c4249; line-height: 1.55; }
    .agdl-md summary { cursor: pointer; font-size: 12px; color: #0f7f96; }
    .agdl-md h1, .agdl-md h2, .agdl-md h3 { font-size: 13px; font-weight: 600; margin: 10px 0 4px; color: #14171b; }
    .agdl-md ul, .agdl-md ol { padding-left: 18px; margin: 4px 0; }
    .agdl-md ul { list-style: disc; } .agdl-md ol { list-style: decimal; }
    .agdl-md code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; background: #e6e9ee; border-radius: 4px; padding: 0 4px; }
    .agdl-md pre { background: #14171b; color: #e6e9ee; border-radius: 8px; padding: 8px 12px; overflow-x: auto; }
    .agdl-md pre code { background: none; padding: 0; color: inherit; }
    .agdl-md a { color: #0f7f96; text-decoration: underline; }
    .agdl-link { font-size: 12px; color: #0f7f96; text-decoration: underline; }
</style>

<div class="agdl">
    <div class="agdl-head">
        <span class="agdl-title">Release history</span>
        <span class="agdl-muted">Latest first · keeps the last {{ $max }} versions ·
            <a class="agdl-link" href="{{ route('releases', ['product' => $productKey]) }}" target="_blank">Public release notes</a></span>
    </div>

    @forelse ($releases as $i => $release)
        <div class="agdl-card">
            <div class="agdl-row">
                <span class="agdl-ver">v{{ $release['version'] }}</span>
                <span class="agdl-badge {{ $i === 0 ? 'latest' : '' }}">
                    {{ $i === 0 ? 'Latest' : ($i === 1 ? 'Previous (latest − 1)' : 'Older') }}
                </span>
                @if (! empty($release['type']))
                    <span class="agdl-badge type {{ $release['type'] === 'security' ? 'security' : '' }}">{{ \App\Support\Downloads::RELEASE_TYPES[$release['type']] ?? $release['type'] }}</span>
                @endif
                @if (! empty($release['released_at']))
                    <span class="agdl-muted">{{ \Illuminate\Support\Carbon::parse($release['released_at'])->format('d M Y') }}</span>
                @endif
                <span class="agdl-actions">
                    {{ ($this->editReleaseAction)(['product' => $productKey, 'index' => $i]) }}
                    {{ ($this->deleteReleaseAction)(['product' => $productKey, 'index' => $i]) }}
                </span>
            </div>

            @if (! empty($release['title']))
                <div class="agdl-rtitle">{{ $release['title'] }}</div>
            @endif
            @if (! empty($release['summary']))
                <div class="agdl-summary">{{ $release['summary'] }}</div>
            @endif

            @if ($withChecksum)
            <div class="agdl-label" style="margin-top:0">SHA-256</div>
            <code class="agdl-code">{{ $release['checksum'] ?: '—' }}</code>

            @if ($cmd = $commandFor($release))
                <div class="agdl-label">Validate{{ isset($product['platforms']) ? ' (Linux)' : '' }}</div>
                <code class="agdl-cmd">{{ $cmd }}</code>
            @endif
            @if (isset($product['platforms']) && ($win = \App\Support\Downloads::validateCommandWindows($product, $release)))
                <div class="agdl-label">Validate (Windows PowerShell)</div>
                <code class="agdl-cmd">{{ $win }}</code>
            @endif
            @endif

            @if (! empty($release['notes']))
                <details class="agdl-md" @if ($i === 0) open @endif>
                    <summary>Release notes</summary>
                    {!! \App\Support\Markdown::toHtml($release['notes']) !!}
                </details>
            @else
                <div class="agdl-notes">No release notes yet. Use Edit to write them.</div>
            @endif
        </div>
    @empty
        <div class="agdl-muted">No releases yet. Publish the first one above.</div>
    @endforelse
</div>
