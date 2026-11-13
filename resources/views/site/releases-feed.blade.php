@php
    use App\Support\Downloads;
    use Illuminate\Support\Carbon;
    $base = rtrim(config('atglance.frontend_url'), '/');
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
@endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>AtGlance release notes</title>
    <link>{{ $base }}/releases</link>
    <atom:link href="{{ $base }}/releases/rss.xml" rel="self" type="application/rss+xml" />
    <description>New versions of the AtGlance CLI and the Management Console.</description>
    <language>en</language>
@foreach ($releases as $r)
    @php($url = $base.'/releases/'.$r['product'].'/'.rawurlencode($r['version']))
    <item>
        <title>{{ $r['product_name'] }} v{{ $r['version'] }}{{ $r['title'] ? ' — '.$r['title'] : '' }}</title>
        <link>{{ $url }}</link>
        <guid isPermaLink="true">{{ $url }}</guid>
        <category>{{ $r['product'] === 'cli' ? 'CLI' : 'Console' }}</category>
@if ($r['released_at'])
        <pubDate>{{ Carbon::parse($r['released_at'])->toRfc2822String() }}</pubDate>
@endif
        <description>{{ $r['summary'] ?: ($r['notes'] ? \App\Support\Markdown::toHtml($r['notes']) : 'Maintenance release.') }}</description>
    </item>
@endforeach
</channel>
</rss>
