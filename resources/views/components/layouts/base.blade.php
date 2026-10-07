{{-- HTML shell shared by every public and dashboard page. --}}
@props(['title' => null, 'description' => null])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · AtGlance' : 'AtGlance — Operations, AtGlance. Inside your boundary.' }}</title>
    <meta name="description" content="{{ $description ?? 'AtGlance — a self-hosted operations platform for SREs. CLI + Management Console, inside your boundary.' }}">
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
