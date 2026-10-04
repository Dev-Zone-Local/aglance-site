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
</style>

<div class="agdl">
    <div class="agdl-head">
        <span class="agdl-title">Release history</span>
        <span class="agdl-muted">Latest first · keeps the last {{ $max }} versions</span>
    </div>

    @forelse ($releases as $i => $release)
        <div class="agdl-card">
            <div class="agdl-row">
                <span class="agdl-ver">v{{ $release['version'] }}</span>
                <span class="agdl-badge {{ $i === 0 ? 'latest' : '' }}">
                    {{ $i === 0 ? 'Latest' : ($i === 1 ? 'Previous (latest − 1)' : 'Older') }}
                </span>
                @if (! empty($release['released_at']))
                    <span class="agdl-muted">{{ \Illuminate\Support\Carbon::parse($release['released_at'])->format('d M Y, H:i') }}</span>
                @endif
                <span class="agdl-actions">
                    {{ ($this->editReleaseAction)(['product' => $productKey, 'index' => $i]) }}
                    {{ ($this->deleteReleaseAction)(['product' => $productKey, 'index' => $i]) }}
                </span>
            </div>

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
                <div class="agdl-notes">{{ $release['notes'] }}</div>
            @endif
        </div>
    @empty
        <div class="agdl-muted">No releases yet. Publish the first one above.</div>
    @endforelse
</div>
