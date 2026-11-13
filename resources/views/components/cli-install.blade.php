{{--
    Install tabs for the latest CLI files: Debian/Ubuntu, RHEL/Fedora, other Linux, Windows.
    Each tab only appears when that file is uploaded in Admin → Downloads.
--}}
@props(['files'])
@php
    $find = fn (string $suffix, array $arch = []) => $files->first(fn ($f) => str_ends_with(strtolower($f->file_name), $suffix) && (! $arch || in_array($f->arch, $arch, true)));
    $x64 = ['amd64', 'x86_64'];
    $deb = $find('.deb', $x64) ?? $find('.deb');
    $rpm = $find('.rpm', $x64) ?? $find('.rpm');
    $tar = $find('.tar.gz', $x64) ?? $find('.tar.gz');
    $winFiles = $files->where('platform', 'windows');
    $win = $winFiles->first(fn ($f) => str_ends_with(strtolower($f->file_name), '.msi'))
        ?? $winFiles->first(fn ($f) => str_ends_with(strtolower($f->file_name), '.zip'))
        ?? $winFiles->first();
    $tarDir = $tar ? preg_replace('/\.(tar\.gz|tgz)$/', '', $tar->file_name) : null;
    $archs = function (string $suffix) use ($files) {
        $list = $files->filter(fn ($f) => str_ends_with(strtolower($f->file_name), $suffix))->pluck('arch')->filter()->unique()->implode(', ');

        return $list ? " Architectures: {$list}." : '';
    };

    $winCode = null;
    if ($win) {
        $ext = strtolower(pathinfo($win->file_name, PATHINFO_EXTENSION));
        $winCode = 'Invoke-WebRequest '.$win->url().' -OutFile '.$win->file_name."\n".match ($ext) {
            'msi' => 'msiexec /i '.$win->file_name,
            'zip' => 'Expand-Archive '.$win->file_name.' -DestinationPath atglance'."\n".'.\atglance\install.ps1',
            default => "New-Item -ItemType Directory -Force 'C:\Program Files\AtGlance' | Out-Null\n"
                .'Copy-Item .\\'.$win->file_name." 'C:\Program Files\AtGlance\atglance.exe'",
        };
    }

    // macOS: .pkg, then a .tar.gz, then the plain binary (Apple silicon first).
    $macFiles = $files->where('platform', 'macos');
    $mac = $macFiles->first(fn ($f) => str_ends_with(strtolower($f->file_name), '.pkg'))
        ?? $macFiles->first(fn ($f) => str_ends_with(strtolower($f->file_name), '.tar.gz') && $f->arch === 'arm64')
        ?? $macFiles->first(fn ($f) => str_ends_with(strtolower($f->file_name), '.tar.gz'))
        ?? $macFiles->firstWhere('arch', 'arm64')
        ?? $macFiles->first();
    $macCode = null;
    if ($mac) {
        $n = strtolower($mac->file_name);
        $macCode = 'curl -fsSLO '.$mac->url()."\n".match (true) {
            str_ends_with($n, '.pkg') => 'sudo installer -pkg '.$mac->file_name.' -target /',
            str_ends_with($n, '.tar.gz') => 'tar -xzf '.$mac->file_name."\n".'sudo install -m 0755 '.preg_replace('/\.tar\.gz$/', '', $mac->file_name).'/atglance /usr/local/bin/atglance',
            default => 'sudo install -m 0755 '.$mac->file_name.' /usr/local/bin/atglance',
        };
    }

    $tabs = array_filter([
        'deb' => $deb ? ['Debian / Ubuntu', 'Ubuntu 20.04+, Debian 11+, Raspberry Pi OS, Linux Mint, Pop!_OS.'.$archs('.deb'), 'bash',
            "curl -fsSLO {$deb->url()}\nsudo apt-get install -y ./{$deb->file_name}"] : null,
        'rpm' => $rpm ? ['RHEL / Fedora', 'RHEL 8+, Rocky, AlmaLinux, Fedora, openSUSE, Amazon Linux 2023.'.$archs('.rpm'), 'bash',
            "curl -fsSLO {$rpm->url()}\nsudo rpm -Uvh {$rpm->file_name}"] : null,
        'tar' => $tar ? ['Other Linux', 'Any Linux with systemd 230 or newer.'.$archs('.tar.gz'), 'bash',
            "curl -fsSLO {$tar->url()}\ntar -xzf {$tar->file_name}\nsudo cp {$tarDir}/atglance /usr/local/bin/"] : null,
        'win' => $win ? ['Windows', 'Windows Server, PowerShell as Administrator. The agent runs as the AtGlance Agent service.', 'PowerShell', $winCode] : null,
        'mac' => $mac ? ['macOS', 'Apple silicon (arm64) or Intel (amd64). The agent runs as a launchd job.'.($macFiles->pluck('arch')->filter()->unique()->isNotEmpty() ? ' Architectures: '.$macFiles->pluck('arch')->filter()->unique()->implode(', ').'.' : ''), 'zsh', $macCode] : null,
    ]);
@endphp
@if ($tabs)
    <div {{ $attributes }} x-data="{ tab: '{{ array_key_first($tabs) }}' }">
        <div class="flex flex-wrap gap-1 rounded-[22px] bg-white p-1 shadow-ag sm:inline-flex sm:rounded-full" role="tablist" aria-label="Operating system">
            @foreach ($tabs as $key => [$label])
                <button type="button" role="tab" id="cli-tab-{{ $key }}" aria-controls="cli-panel-{{ $key }}" x-on:click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-ag-ink text-white' : 'text-ag-subtle hover:text-ag-ink'"
                    class="rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">{{ $label }}</button>
            @endforeach
        </div>
        @foreach ($tabs as $key => [$label, $note, $shell, $code])
            <div id="cli-panel-{{ $key }}" role="tabpanel" aria-labelledby="cli-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if (! $loop->first) x-cloak @endif class="mt-4">
                <p class="text-sm text-ag-subtle">{{ $note }}</p>
                <x-code-block :title="$shell" :code="$code" class="mt-3" />
            </div>
        @endforeach
    </div>
@else
    <p {{ $attributes->class('text-sm text-ag-subtle') }}>No CLI files are published yet. See <a href="{{ route('downloads') }}" class="text-ag-teal-text underline">Downloads</a>.</p>
@endif
