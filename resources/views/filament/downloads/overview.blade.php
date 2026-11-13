{{--
    Top of each product tab in Admin → Downloads: what is published now, per operating system,
    and where it appears. Own styles (Filament does not compile Tailwind classes in custom views).
--}}
@php
    use App\Support\Downloads;

    $latest = $product['releases'][0] ?? null;
    $isCli = $productKey === 'cli';
    $legacyHost = config('atglance.legacy_download_host');
    $legacy = $latest && $legacyHost
        ? ($isCli && preg_match('/^(\d+)\.(\d+)/', $latest['version'], $m) ? "{$legacyHost}/cli/atglance_{$m[1]}.{$m[2]}/" : ($isCli ? null : "{$legacyHost}/console/"))
        : null;
@endphp
<style>
    .agov { display: grid; gap: 14px; }
    .agov-top { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .agov-title { font-size: 18px; font-weight: 500; color: #14171b; letter-spacing: -.01em; }
    .agov-badge { border-radius: 8px; padding: 2px 9px; font-size: 12px; font-weight: 600; background: #dff8ee; color: #138a5c; }
    .agov-badge.none { background: #fff3dc; color: #a06307; }
    .agov-muted { font-size: 13px; color: #5b626b; line-height: 1.55; }
    .agov-grid { display: grid; gap: 12px; grid-template-columns: repeat({{ count($platforms) }}, minmax(0, 1fr)); }
    @media (max-width: 1100px) { .agov-grid { grid-template-columns: 1fr; } }
    .agov-os { background: #fff; border-radius: 16px; padding: 14px 16px; box-shadow: 0 8px 30px rgba(17, 20, 24, .06); }
    .agov-os h4 { display: flex; align-items: center; justify-content: space-between; margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #14171b; }
    .agov-os h4 span { font-size: 12px; font-weight: 500; color: #8a9099; }
    .agov-file { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; padding: 4px 0; border-top: 1px solid #f0f2f5; }
    .agov-file:first-of-type { border-top: 0; }
    .agov-file code { font-family: "JetBrains Mono", ui-monospace, monospace; color: #262a30; word-break: break-all; }
    .agov-file em { font-style: normal; color: #8a9099; white-space: nowrap; }
    .agov-empty { font-size: 12px; color: #a06307; background: #fff8ea; border-radius: 10px; padding: 8px 10px; }
    .agov-where { font-size: 12px; color: #5b626b; background: #f4f6f8; border-radius: 12px; padding: 10px 12px; line-height: 1.7; }
    .agov-where code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11.5px; color: #0f7f96; }
</style>

<div class="agov">
    <div class="agov-top">
        <span class="agov-title">{{ Downloads::PRODUCTS[$productKey] }}</span>
        @if ($latest)
            <span class="agov-badge">Latest v{{ $latest['version'] }}</span>
            @if (! empty($latest['released_at']))<span class="agov-muted">released {{ \Illuminate\Support\Carbon::parse($latest['released_at'])->format('d M Y') }}</span>@endif
        @else
            <span class="agov-badge none">Nothing published yet</span>
        @endif
    </div>
    <div class="agov-muted">
        @if ($isCli)
            The CLI runs on every server you monitor. Publish a version in step 1 and upload its packages for each operating system in step 2. Health commands and config backups work on Linux; Windows and macOS run the agent.
        @else
            The Management Console is installed once per organization with these scripts. Publish a version in step 1 and upload the installer and update scripts in step 2.
        @endif
    </div>

    <div class="agov-grid">
        @foreach ($platforms as $os => $osLabel)
            @php($osFiles = $files->where('platform', $os))
            <div class="agov-os">
                <h4>{{ $osLabel }} <span>{{ $osFiles->count() }} {{ $osFiles->count() === 1 ? 'file' : 'files' }} in v{{ $latest['version'] ?? '—' }}</span></h4>
                @forelse ($osFiles->take(6) as $f)
                    <div class="agov-file"><code>{{ $f->file_name }}</code><em>{{ $f->arch ?? $f->typeLabel() }} · {{ $f->humanSize() }}</em></div>
                @empty
                    <div class="agov-empty">No {{ $osLabel }} {{ $isCli ? 'package' : 'script' }} in the latest release. Upload one in step 2 (with a new version) or with "Add files" in step 3.</div>
                @endforelse
                @if ($osFiles->count() > 6)<div class="agov-muted" style="font-size:12px">and {{ $osFiles->count() - 6 }} more</div>@endif
            </div>
        @endforeach
    </div>

    <div class="agov-where">
        <strong>Where the files appear:</strong>
        <code>/downloads</code>, <code>/{{ $productKey }}</code>, the release notes (<code>/releases/{{ $productKey }}/&lt;version&gt;</code>), the dashboard installer and <code>/api/downloads</code>.
        Each file is also at <code>/downloads/files/{{ $productKey }}/&lt;version&gt;/&lt;file&gt;</code>@if (! $isCli), and the newest of a name at <code>/downloads/latest/console/&lt;file&gt;</code>@endif.
        @if ($legacy)<br>Old links keep working: <code>{{ $legacy }}&lt;file&gt;</code>@endif
    </div>
</div>
