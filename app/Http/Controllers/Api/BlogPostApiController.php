<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogApiKey;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Blog API for automation (n8n, scripts): create, update, publish and delete posts.
 * Auth: BlogApiKeyAuth (Bearer agb_...). Documentation: docs/blog-api.md.
 *
 * A post is found by id or by slug. Cover images come as a file upload ("cover_image"),
 * base64 ("cover_image_base64"), or an https link ("cover_image_url") that is downloaded once.
 */
class BlogPostApiController extends Controller
{
    private const IMAGE_MAX_BYTES = 5 * 1024 * 1024;

    /** Host name to IP addresses. Tests replace it; null = the system resolver. */
    public static ?\Closure $resolver = null;

    private const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = BlogPost::query()
            ->when($request->query('status') === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($request->query('status') === 'published', fn ($q) => $q->published())
            ->when($request->query('status') === 'scheduled', fn ($q) => $q->where('status', 'published')->where('published_at', '>', now()))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->when($request->query('search'), fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->latest('updated_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $posts->getCollection()->map(fn (BlogPost $p) => $this->present($p, false))->all(),
            'meta' => ['page' => $posts->currentPage(), 'per_page' => $posts->perPage(), 'total' => $posts->total(), 'last_page' => $posts->lastPage()],
        ]);
    }

    public function show(string $post): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($post))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $post = new BlogPost;
        $this->fill($post, $data, $request);
        $post->author_id ??= $this->key($request)->created_by;
        $post->save();

        return response()->json(['data' => $this->present($post->fresh('author'))], 201)
            ->header('Location', url('/api/blog/posts/'.$post->id));
    }

    public function update(Request $request, string $post): JsonResponse
    {
        $model = $this->find($post);
        $data = $this->validated($request, $model);
        $this->fill($model, $data, $request);
        $model->save();

        return response()->json(['data' => $this->present($model->fresh('author'))]);
    }

    /** Publish now, or at "published_at" (a future date schedules it). */
    public function publish(Request $request, string $post): JsonResponse
    {
        $model = $this->find($post);
        $data = $request->validate(['published_at' => ['nullable', 'date']]);
        $model->status = 'published';
        $model->published_at = isset($data['published_at']) ? $data['published_at'] : ($model->published_at && $model->published_at->isFuture() ? $model->published_at : now());
        $model->save();

        return response()->json(['data' => $this->present($model->fresh('author'))]);
    }

    public function unpublish(string $post): JsonResponse
    {
        $model = $this->find($post);
        $model->status = 'draft';
        $model->save();

        return response()->json(['data' => $this->present($model->fresh('author'))]);
    }

    public function destroy(string $post): JsonResponse
    {
        $model = $this->find($post);
        if ($model->cover_image) {
            Storage::disk('public')->delete($model->cover_image);
        }
        $model->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /** Upload an image to use inside a post body: returns its URL and a Markdown snippet. */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:'.(self::IMAGE_MAX_BYTES / 1024)],
            'image_base64' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:https', 'max:2000'],
            'alt' => ['nullable', 'string', 'max:200'],
        ]);
        $path = $this->storeImage($request, 'image', 'blog/attachments');
        if (! $path) {
            throw ValidationException::withMessages(['image' => 'Send "image" (file), "image_base64" or "image_url".']);
        }
        $url = asset('storage/'.$path);

        return response()->json(['data' => ['path' => $path, 'url' => $url, 'markdown' => '!['.($request->input('alt') ?? '').']('.$url.')']], 201);
    }

    private function key(Request $request): BlogApiKey
    {
        return $request->attributes->get('blogApiKey');
    }

    /** By numeric id, or by slug. */
    private function find(string $post): BlogPost
    {
        $found = ctype_digit($post) ? BlogPost::find((int) $post) : null;
        $found ??= BlogPost::firstWhere('slug', $post);
        abort_unless($found, 404, 'Post not found.');

        return $found->loadMissing('author');
    }

    private function validated(Request $request, ?BlogPost $post): array
    {
        // n8n often sends tags as one comma-separated string.
        if (is_string($request->input('tags'))) {
            $request->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $request->input('tags'))), 'strlen'))]);
        }
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        }

        $creating = $post === null;

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'body' => [$creating ? 'required' : 'sometimes', 'string', 'max:200000'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:300'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'status' => ['sometimes', Rule::in(array_keys(BlogPost::STATUSES))],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:170'],
            'cover_image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:'.(self::IMAGE_MAX_BYTES / 1024)],
            'cover_image_base64' => ['sometimes', 'nullable', 'string'],
            'cover_image_url' => ['sometimes', 'nullable', 'url:https', 'max:2000'],
            'remove_cover_image' => ['sometimes', 'boolean'],
        ], [
            'slug.unique' => 'A post with this slug already exists. Update it with PATCH /api/blog/posts/{slug} instead.',
        ]);
    }

    private function fill(BlogPost $post, array $data, Request $request): void
    {
        foreach (['title', 'body', 'slug', 'excerpt', 'tags', 'status', 'published_at', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $data)) {
                $post->{$field} = $data[$field];
            }
        }

        $old = $post->cover_image;
        if (! empty($data['remove_cover_image'])) {
            $post->cover_image = null;
        }
        if ($path = $this->storeImage($request, 'cover_image', 'blog')) {
            $post->cover_image = $path;
        }
        if ($old && $old !== $post->cover_image) {
            Storage::disk('public')->delete($old);
        }
    }

    /** Store "{field}" (file), "{field}_base64" or "{field}_url" on the public disk. */
    private function storeImage(Request $request, string $field, string $dir): ?string
    {
        if ($request->file($field) instanceof UploadedFile) {
            return $request->file($field)->store($dir, 'public');
        }

        if ($request->filled("{$field}_base64")) {
            $raw = (string) $request->input("{$field}_base64");
            $raw = preg_replace('/^data:image\/[a-z+]+;base64,/i', '', $raw);
            $bytes = base64_decode($raw, true);
            if ($bytes === false) {
                throw ValidationException::withMessages(["{$field}_base64" => 'Not valid base64.']);
            }

            return $this->storeBytes($bytes, $dir, "{$field}_base64");
        }

        if ($request->filled("{$field}_url")) {
            return $this->storeBytes($this->download((string) $request->input("{$field}_url"), "{$field}_url"), $dir, "{$field}_url");
        }

        return null;
    }

    private function storeBytes(string $bytes, string $dir, string $field): string
    {
        if (strlen($bytes) > self::IMAGE_MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'The image is larger than 5 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! isset(self::IMAGE_TYPES[$mime])) {
            throw ValidationException::withMessages([$field => 'Only PNG, JPG, WebP or GIF images are accepted.']);
        }
        $path = $dir.'/'.Str::random(32).'.'.self::IMAGE_TYPES[$mime];
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** Download an https image. Refuses addresses inside private networks (no requests to internal services). */
    private function download(string $url, string $field): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = self::$resolver ? (self::$resolver)($host) : (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []));
        foreach ($ips ?: ['0.0.0.0'] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw ValidationException::withMessages([$field => 'This address cannot be downloaded from.']);
            }
        }

        try {
            // Connect to the address checked above, not to a fresh DNS answer.
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
            $response = Http::timeout(15)->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]],
            ])->get($url);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'The image could not be downloaded.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages([$field => 'The image could not be downloaded (HTTP '.$response->status().').']);
        }

        return $response->body();
    }

    private function present(BlogPost $p, bool $withBody = true): array
    {
        $state = $p->isPublic() ? 'published' : ($p->status === 'published' ? 'scheduled' : 'draft');

        return [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'status' => $p->status,
            'state' => $state,
            'url' => $p->url(),
            'excerpt' => $p->excerpt,
            'summary' => $p->summary(),
            ...($withBody ? ['body' => $p->body] : []),
            'tags' => $p->tags ?? [],
            'cover_image_url' => $p->coverUrl(),
            'seo_title' => $p->seo_title,
            'seo_description' => $p->seo_description,
            'author' => $p->author?->name,
            'published_at' => $p->published_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
