{{-- Table of the files of one release: platform, architecture, size, SHA-256 and a download link. --}}
@props(['files', 'caption' => null])
@if ($files->isNotEmpty())
    <div {{ $attributes->class('overflow-x-auto rounded-card bg-white shadow-ag') }}>
        <table class="w-full text-sm">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            <thead>
                <tr class="bg-ag-surface text-left text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">
                    <th scope="col" class="px-5 py-3">File</th>
                    <th scope="col" class="px-5 py-3">Platform</th>
                    <th scope="col" class="hidden px-5 py-3 sm:table-cell">Size</th>
                    <th scope="col" class="hidden px-5 py-3 lg:table-cell">SHA-256</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Download</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($files as $f)
                    <tr class="border-t border-ag-line align-top">
                        <td class="px-5 py-3">
                            <div class="break-all font-mono text-[13px] text-ag-ink">{{ $f->file_name }}</div>
                            <div class="mt-0.5 text-xs text-ag-subtle">{{ $f->typeLabel() }}</div>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-ag-subtle">
                            {{ \App\Models\DownloadFile::PLATFORMS[$f->platform] ?? $f->platform }}@if ($f->arch) · <span class="font-mono text-xs">{{ $f->arch }}</span>@endif
                        </td>
                        <td class="hidden whitespace-nowrap px-5 py-3 text-ag-subtle sm:table-cell">{{ $f->humanSize() }}</td>
                        <td class="hidden px-5 py-3 lg:table-cell">
                            <code class="block max-w-[260px] truncate font-mono text-xs text-ag-subtle" title="{{ $f->sha256 }}">{{ $f->sha256 }}</code>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ $f->url() }}" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-ag-surface px-3 py-1.5 text-[13px] font-medium text-ag-ink transition-colors hover:bg-ag-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal"
                                aria-label="Download {{ $f->file_name }}">
                                <x-glyph name="download" :size="14" /> Download
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
