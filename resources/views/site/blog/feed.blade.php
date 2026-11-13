@php
    $base = rtrim(config('atglance.frontend_url'), '/');
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
@endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>AtGlance {{ $settings['title'] }}</title>
    <link>{{ $base }}/blog</link>
    <atom:link href="{{ $base }}/blog/rss.xml" rel="self" type="application/rss+xml" />
    <description>{{ $settings['intro'] }}</description>
    <language>en</language>
@foreach ($posts as $post)
    @php($url = $base.'/blog/'.rawurlencode($post->slug))
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ $url }}</link>
        <guid isPermaLink="true">{{ $url }}</guid>
        <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
@foreach ($post->tags ?? [] as $t)
        <category>{{ $t }}</category>
@endforeach
        <description>{{ $post->summary(400) }}</description>
    </item>
@endforeach
</channel>
</rss>
