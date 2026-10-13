{{-- Bottom of the admin sidebar: running site version (App\Support\SiteVersion). --}}
@php($site = \App\Support\SiteVersion::current())
<div class="ag-site-version" title="{{ \App\Support\SiteVersion::label($site) }}"
    style="padding: .75rem 1.5rem; font-size: .75rem; line-height: 1.4; color: #64748B; border-top: 1px solid rgba(15, 23, 42, .08);">
    Site version <strong style="color: #0F172A; font-weight: 600;">{{ $site['version'] }}</strong>
    @if ($site['commit'])
        <div style="font-family: ui-monospace, monospace;">build {{ $site['commit'] }}@if ($site['branch']) · {{ $site['branch'] }}@endif</div>
    @endif
</div>
