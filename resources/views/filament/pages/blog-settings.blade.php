<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">Save</x-filament::button>
        </div>
    </form>

    {{-- Blog API keys (own styles: Filament does not compile Tailwind classes in custom views). --}}
    <style>
        .agk { background: #fff; border-radius: 20px; padding: 20px 22px; box-shadow: 0 8px 30px rgba(17, 20, 24, .06); }
        .agk-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .agk-head h2 { font-size: 16px; font-weight: 500; margin: 0; color: #14171b; }
        .agk-head p { flex-basis: 100%; margin: 0; font-size: 13px; color: #5b626b; line-height: 1.55; }
        .agk-head .act { margin-left: auto; }
        .agk-new { margin-top: 14px; border-radius: 14px; background: #ecfbf5; padding: 12px 14px; font-size: 13px; color: #14532d; }
        .agk-new code { display: block; margin-top: 6px; padding: 8px 10px; border-radius: 8px; background: #14171b; color: #71f7d4; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 13px; word-break: break-all; user-select: all; }
        .agk-table { width: 100%; margin-top: 14px; border-collapse: collapse; font-size: 13px; }
        .agk-table th { text-align: left; font-size: 11px; font-weight: 500; color: #8a9099; padding: 6px 8px; background: #f4f6f8; }
        .agk-table td { padding: 9px 8px; border-top: 1px solid #f0f2f5; color: #262a30; }
        .agk-table code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; }
        .agk-muted { color: #8a9099; }
        .agk-doc { margin-top: 16px; font-size: 12.5px; color: #5b626b; background: #f4f6f8; border-radius: 12px; padding: 12px 14px; line-height: 1.8; }
        .agk-doc code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; color: #0f7f96; }
    </style>

    <section class="agk" aria-labelledby="agk-title">
        <div class="agk-head">
            <h2 id="agk-title">API keys</h2>
            <span class="act">{{ $this->createKeyAction }}</span>
            <p>Let a workflow such as n8n create and publish posts through the Blog API. Each key works only for the blog. Revoke a key to stop a workflow.</p>
        </div>

        @if ($newKey)
            <div class="agk-new" role="status">
                <strong>Copy this key now.</strong> It is shown only once; only a fingerprint of it is stored.
                <code>{{ $newKey }}</code>
            </div>
        @endif

        @php($keys = $this->getApiKeys())
        @if ($keys->isNotEmpty())
            <table class="agk-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Key</th><th scope="col">Created</th><th scope="col">Last used</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach ($keys as $key)
                        <tr>
                            <td>{{ $key->name }}</td>
                            <td><code>{{ $key->masked() }}</code></td>
                            <td class="agk-muted">{{ $key->created_at->format('d M Y') }}{{ $key->creator ? ' · '.$key->creator->name : '' }}</td>
                            <td class="agk-muted">{{ $key->last_used_at ? $key->last_used_at->diffForHumans().' · '.$key->last_used_ip : 'Never' }}</td>
                            <td style="text-align:right">{{ ($this->revokeKeyAction)(['id' => $key->id]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="agk-muted" style="margin:12px 0 0;font-size:13px">No API keys yet.</p>
        @endif

        <div class="agk-doc">
            <strong>Quick reference</strong> · header <code>Authorization: Bearer agb_…</code> · JSON or multipart<br>
            <code>POST {{ url('/api/blog/posts') }}</code> create (title, body in Markdown, status "draft" or "published", tags, excerpt, cover_image_url…)<br>
            <code>PATCH /api/blog/posts/{id|slug}</code> change · <code>POST /api/blog/posts/{id|slug}/publish</code> publish · <code>…/unpublish</code> · <code>DELETE /api/blog/posts/{id|slug}</code><br>
            <code>GET /api/blog/posts</code> list · <code>POST /api/blog/images</code> image for inside a post (returns a Markdown snippet)<br>
            Full guide with n8n steps: <code>docs/blog-api.md</code> in the site repository.
        </div>
    </section>

</x-filament-panels::page>
