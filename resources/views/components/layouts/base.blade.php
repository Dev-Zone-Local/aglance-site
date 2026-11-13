{{-- HTML shell shared by every public and dashboard page. --}}
@props(['title' => null, 'description' => null, 'image' => null, 'ogType' => 'website', 'noindex' => false])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Titles, descriptions, sharing image and indexing: Admin → SEO (App\Support\Seo). --}}
    @php($seo = \App\Support\Seo::meta($title, $description, request()->path(), $image))
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @if ($seo['noindex'] || ! empty($noindex))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $seo['site_name'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    @if ($seo['twitter'])
        <meta name="twitter:site" content="{{ $seo['twitter'] }}">
    @endif
    @if ($seo['google'])
        <meta name="google-site-verification" content="{{ $seo['google'] }}">
    @endif
    @if ($seo['bing'])
        <meta name="msvalidate.01" content="{{ $seo['bing'] }}">
    @endif
    <link rel="alternate" type="application/rss+xml" title="AtGlance release notes" href="{{ route('releases.feed') }}">
    @if (\App\Support\Blog::enabled() && \App\Support\Blog::settings()['rss'])
        <link rel="alternate" type="application/rss+xml" title="AtGlance blog" href="{{ route('blog.feed') }}">
    @endif
    <meta name="theme-color" content="#EEF1F4">
    <link rel="icon" href="/branding/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/branding/favicon-32.png">
    <link rel="apple-touch-icon" href="/branding/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:shadow-ag">Skip to content</a>
    {{ $slot }}
    <x-flash />
    @livewireScriptConfig
</body>
</html>
