{{-- Markdown from the CMS (docs, pages, install steps). Raw HTML is stripped. --}}
@props(['source' => ''])
<div {{ $attributes->class('prose-atg') }}>{!! \App\Support\Markdown::toHtml($source) !!}</div>
