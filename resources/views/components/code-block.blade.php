{{-- Dark code block with a copy button. Pass the code as :code or in the slot. --}}
@props(['code' => null, 'title' => 'bash'])
<div x-data="copy" {{ $attributes->class('relative overflow-hidden rounded-input bg-[#14171B]') }}>
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-2">
        <span class="font-mono text-[11px] text-[#8A9099]">{{ $title }}</span>
        <button type="button" x-on:click="copy" class="flex items-center gap-1.5 text-xs text-[#B7BEC6] transition-colors hover:text-[#71F7D4] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#71F7D4]">
            <span x-show="!copied" class="inline-flex items-center gap-1.5"><x-glyph name="copy" :size="12" /> Copy</span>
            <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5"><x-glyph name="check" :size="12" /> Copied</span>
        </button>
    </div>
    <pre class="m-0 overflow-x-auto p-4 font-mono text-[13px] leading-relaxed text-[#E6E9EE]"><code x-ref="text">{{ $code ?? $slot }}</code></pre>
</div>
