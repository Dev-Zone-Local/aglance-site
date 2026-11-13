{{--
    Share a page: the device's own share sheet (phones), copy link, and plain share links.
    No third-party scripts or trackers: every button is an ordinary link opened in a new tab.
    Usage: <x-share-buttons :url="$post->url()" :title="$post->title" :text="$post->summary(120)" />
--}}
@props(['url', 'title', 'text' => null, 'label' => 'Share'])
@php
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $links = [
        ['X', 'twitter', "https://twitter.com/intent/tweet?url={$u}&text={$t}"],
        ['LinkedIn', 'linkedin', "https://www.linkedin.com/sharing/share-offsite/?url={$u}"],
        ['Facebook', null, "https://www.facebook.com/sharer/sharer.php?u={$u}"],
        ['Reddit', null, "https://www.reddit.com/submit?url={$u}&title={$t}"],
        ['WhatsApp', 'message-circle', 'https://wa.me/?text='.rawurlencode($title.' '.$url)],
        ['Email', 'mail', 'mailto:?subject='.$t.'&body='.rawurlencode(($text ? $text."\n\n" : '').$url)],
    ];
    $btn = 'inline-flex h-9 items-center gap-1.5 rounded-full bg-white px-3 text-[13px] font-medium text-ag-ink shadow-ag ring-1 ring-ag-line transition-colors hover:bg-ag-surface hover:text-ag-teal-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal';
@endphp
<div {{ $attributes->class('flex flex-wrap items-center gap-2') }}
    x-data="{ copied: false, canShare: typeof navigator.share === 'function',
        async copy() { try { await navigator.clipboard.writeText(@js($url)); this.copied = true; setTimeout(() => this.copied = false, 2000) } catch (e) {} },
        async share() { try { await navigator.share({ title: @js($title), text: @js($text ?? $title), url: @js($url) }) } catch (e) {} } }"
    role="group" aria-label="{{ $label }} this post">
    <span class="mr-1 text-sm text-ag-subtle">{{ $label }}</span>

    <button type="button" x-show="canShare" x-cloak x-on:click="share()" class="{{ $btn }}">
        <x-glyph name="external-link" :size="14" /> Share…
    </button>

    <button type="button" x-on:click="copy()" class="{{ $btn }}">
        <span x-show="!copied" class="inline-flex items-center gap-1.5"><x-glyph name="copy" :size="14" /> Copy link</span>
        <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5 text-ag-success-text"><x-glyph name="check" :size="14" /> Copied</span>
    </button>
    <span class="sr-only" role="status" x-text="copied ? 'Link copied' : ''"></span>

    @foreach ($links as [$name, $icon, $href])
        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="{{ $btn }}" aria-label="Share on {{ $name }}" title="Share on {{ $name }}">
            @if ($icon)<x-glyph :name="$icon" :size="14" />@endif
            <span @class(['hidden sm:inline' => $icon])>{{ $name }}</span>
        </a>
    @endforeach
</div>
