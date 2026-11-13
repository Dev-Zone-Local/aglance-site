<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\Blog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Public blog: /blog, /blog/{slug}, /blog/rss.xml. Off (404) when Admin → Blog settings turns it off. */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Blog::enabled(), 404);
        $settings = Blog::settings();
        $tag = trim((string) $request->query('tag')) ?: null;

        $posts = BlogPost::published()
            ->with('author:id,name')
            ->when($tag, fn ($q) => $q->whereJsonContains('tags', $tag))
            ->latest('published_at')
            ->paginate((int) $settings['per_page'])
            ->withQueryString();

        $tags = BlogPost::published()->pluck('tags')->flatten()->filter()->countBy()->sortDesc()->take(12)->keys();

        return view('site.blog.index', ['posts' => $posts, 'settings' => $settings, 'tag' => $tag, 'tags' => $tags]);
    }

    /** Admins also see drafts and scheduled posts (marked as a preview, never indexed). */
    public function show(string $slug): View
    {
        abort_unless(Blog::enabled() || auth()->user()?->isAdmin(), 404);
        $post = BlogPost::with('author:id,name')->where('slug', $slug)->firstOrFail();
        $preview = ! $post->isPublic();
        abort_if($preview && ! auth()->user()?->isAdmin(), 404);

        $more = BlogPost::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get();

        return view('site.blog.show', ['post' => $post, 'preview' => $preview, 'more' => $more, 'settings' => Blog::settings()]);
    }

    public function feed(): Response
    {
        abort_unless(Blog::enabled() && Blog::settings()['rss'], 404);

        return response()->view('site.blog.feed', [
            'posts' => BlogPost::published()->latest('published_at')->limit(30)->get(),
            'settings' => Blog::settings(),
        ])->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
