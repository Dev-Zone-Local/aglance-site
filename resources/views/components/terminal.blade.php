{{-- Dark terminal window. Lines starting with "$" or "#" get a mint prompt. --}}
@props(['lines' => [], 'title' => 'atglance@host'])
<div {{ $attributes->class('relative overflow-hidden rounded-card bg-[#14171B] shadow-ag-strong') }}>
    <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
        <span class="h-2.5 w-2.5 rounded-full bg-[#E45757]/80"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-[#D98A0B]/80"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-[#1FA874]/80"></span>
        <span class="ml-3 font-mono text-[11px] text-[#8A9099]">{{ $title }}</span>
        <span class="ml-auto font-mono text-[10px] text-[#8A9099]">bash</span>
    </div>
    <pre class="m-0 overflow-x-auto p-5 font-mono text-[13px] leading-relaxed text-[#E6E9EE]">@foreach ($lines as $line)@php($prompt = str_starts_with($line, '$') || str_starts_with($line, '#'))<div class="whitespace-pre">@if ($prompt)<span class="text-[#71F7D4]">{{ $line[0] }} </span><span class="text-white">{{ substr($line, 2) }}</span>@else<span class="text-[#B7BEC6]">{{ $line }}</span>@endif</div>@endforeach</pre>
</div>
