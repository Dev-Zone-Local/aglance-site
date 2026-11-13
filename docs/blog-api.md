# Blog API

Create, update and publish blog posts on atglance.live from a script or an automation tool such as n8n.
Posts appear on `/blog`, in the blog RSS feed and in the sitemap, exactly like posts written in the admin panel.

Base URL: `https://atglance.live/api/blog`

## 1. Create a key

Admin panel → **Blog → Blog settings → API keys → Create API key**. Give it a name (for example
"n8n blog workflow") and copy the key (`agb_…`). It is shown only once; only a fingerprint is stored.

- A key works only for the Blog API. It cannot sign in, read users or touch licences.
- Posts created with a key are written as the admin who created the key.
- **Revoke** stops it at once. "Last used" shows when and from which address it was used.

Send the key in every request:

```
Authorization: Bearer agb_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

Limit: 120 requests a minute per key address.

## 2. Endpoints

`{post}` is the post's numeric `id` or its `slug`.

| Method and path | What it does |
|---|---|
| `GET /posts` | List posts, newest change first. Filters: `status=draft\|published\|scheduled`, `tag=`, `search=`, `per_page=` (1–100), `page=` |
| `GET /posts/{post}` | One post, with its Markdown body |
| `POST /posts` | Create a post (201) |
| `PATCH /posts/{post}` (or `PUT`, or `POST /posts/{post}/update`) | Change only the fields you send |
| `POST /posts/{post}/publish` | Publish now, or at `published_at` (a future date schedules it) |
| `POST /posts/{post}/unpublish` | Back to draft |
| `DELETE /posts/{post}` | Delete the post and its cover image |
| `POST /images` | Upload an image to use inside a post body; returns its URL and a Markdown snippet |

### Post fields

| Field | Type | Notes |
|---|---|---|
| `title` | string, max 200 | Required to create |
| `body` | Markdown string | Required to create. Headings, lists, links, code blocks, images |
| `slug` | string | Optional. Made from the title when empty. Must be unique |
| `excerpt` | string, max 300 | Summary on the blog list. Empty = start of the body |
| `tags` | array of strings, or one comma-separated string | Max 12 |
| `status` | `draft` (default) or `published` | |
| `published_at` | ISO 8601 date | With `published`: a future date schedules the post |
| `seo_title` | string, max 70 | Title for search engines. Empty = the title |
| `seo_description` | string, max 170 | Description for search engines. Empty = the excerpt |
| `cover_image` | file (multipart) | PNG, JPG, WebP or GIF, max 5 MB. Also the sharing image |
| `cover_image_base64` | base64 or `data:image/png;base64,…` | Same rules |
| `cover_image_url` | `https://` link | Downloaded once and stored on the site. Links to private networks are refused |
| `remove_cover_image` | boolean | Removes the cover |

### Response

```json
{
  "data": {
    "id": 12,
    "title": "Introducing the AtGlance agent",
    "slug": "introducing-the-atglance-agent",
    "status": "published",
    "state": "published",
    "url": "https://atglance.live/blog/introducing-the-atglance-agent",
    "excerpt": "One outbound connection, no open ports.",
    "summary": "One outbound connection, no open ports.",
    "body": "## What it does\n…",
    "tags": ["release", "agent"],
    "cover_image_url": "https://atglance.live/storage/blog/abc.png",
    "seo_title": null,
    "seo_description": null,
    "author": "Ana Admin",
    "published_at": "2026-11-13T09:30:00+00:00",
    "created_at": "2026-11-13T09:30:00+00:00",
    "updated_at": "2026-11-13T09:30:00+00:00"
  }
}
```

`state` is what visitors see: `draft`, `scheduled` or `published`.

Errors: `401` missing or wrong key, `404` post not found, `422` validation (an `errors` object names
each field, for example a slug that already exists), `429` too many requests.

## 3. Examples (curl)

Create a draft:

```bash
curl -X POST https://atglance.live/api/blog/posts \
  -H "Authorization: Bearer $AGB_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"title":"Introducing the AtGlance agent","body":"## What it does\nThe agent connects out.","tags":"release, agent"}'
```

Create and publish in one call, with a cover image from a link:

```bash
curl -X POST https://atglance.live/api/blog/posts \
  -H "Authorization: Bearer $AGB_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"title":"Release 2.3","body":"…","status":"published","cover_image_url":"https://example.com/cover.png"}'
```

Publish a draft by slug:

```bash
curl -X POST https://atglance.live/api/blog/posts/introducing-the-atglance-agent/publish \
  -H "Authorization: Bearer $AGB_KEY" -H "Accept: application/json"
```

Upload a cover image file:

```bash
curl -X POST https://atglance.live/api/blog/posts/12/update \
  -H "Authorization: Bearer $AGB_KEY" -H "Accept: application/json" \
  -F "cover_image=@cover.png"
```

## 4. n8n

1. **Credentials → New → Header Auth.** Name: `Authorization`. Value: `Bearer agb_…`.
2. Add an **HTTP Request** node:
   - Method `POST`, URL `https://atglance.live/api/blog/posts`.
   - Authentication: *Generic Credential Type → Header Auth*, the credential above.
   - Send Headers: `Accept: application/json`.
   - Send Body: *JSON*, for example:
     ```json
     {
       "title": "{{ $json.title }}",
       "body": "{{ $json.markdown }}",
       "excerpt": "{{ $json.summary }}",
       "tags": "{{ $json.tags }}",
       "status": "draft",
       "cover_image_url": "{{ $json.image_url }}"
     }
     ```
     Use n8n's *Using Fields Below* mode instead of raw JSON when the body text contains quotes or new lines; n8n then escapes them.
3. The response has `data.id`, `data.slug` and `data.url`. To publish after a review step, add another
   HTTP Request node: `POST https://atglance.live/api/blog/posts/{{ $json.data.id }}/publish`.
4. For a cover image that is binary data in n8n (for example from an AI image node): Send Body
   *Form-Data*, a field `cover_image` of type *n8n Binary File*, plus `title` and `body` fields. Or
   send the base64 as `cover_image_base64`.
5. A workflow that may run twice should look the post up first: `GET /posts?search=<title>` or
   `GET /posts/<slug>`, then `PATCH` it instead of creating a second one. Creating a post whose slug
   already exists returns `422`.
