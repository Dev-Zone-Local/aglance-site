# Licensing: Management Console licences

A **licence** is created by the user on **Dashboard > Licences** and confirmed with the 5-digit
code we email them (accounts flagged by an admin also need admin approval). A confirmed licence
can then be used in one of two ways, and both follow the same policy:

| Console | How | What happens |
|---|---|---|
| With internet access | paste the **licence key** (`atg_...`) | the console calls `POST /api/licenses/activate` once; atglance.live answers with the licence file for that console |
| Without internet access | generate the **licence file** (`.atgl`) from the licence and upload it | the console checks the file offline |

Either way the console ends up with the same signed licence file (payload v2) and checks it
offline from then on; there is no later online check. The file format is defined in the AtGlance
workspace, `contracts/LICENSE_FORMAT.md`; the console repo's `docs/LICENSING.md` covers the console
side (verification, limits, features, error codes).

**One licence = one console.** An online activation records the console's Installation ID
automatically. A licence file takes the Installation ID as the policy says ("Installation ID for
licence files": required or optional); with an ID the file works on that console only. Either way
the licence is then **in use** (a `license_activations` row; a file without an ID records
`unbound-file`). A licence in use is refused for another console, and the user cannot revoke it
until it expires: revoking would free a slot while the console keeps working offline. AtGlance
Support can still remove it. The key and its file expire on the same date.

Consoles up to 2.2 use only the key and the old JSON answer; they keep working.

## What the site does

1. `App\Support\LicenseFile\Issuer` issues the file for one licence: the licence must be confirmed
   (and approved for flagged accounts) and not expired.
2. `PolicyResolver` picks the policy: the user's custom license, then the plan default, then the
   global default (see "Policies"). It gives `max_nodes`, `max_users`, `features` (the policy's
   **premium** keys only, e.g. `["nfs_storage"]`; free features are never written), the Installation ID rule and the
   activation window (`activate_before` = now + 5 to 180 minutes). The file expires with its
   licence; the licence's expiry is set from the policy's validity when it is created.
3. `PayloadBuilder` produces the exact JSON bytes. `Packer` signs them with Ed25519, encrypts with
   AES-256-GCM (random 12-byte IV, header as AAD), and packs the `.atgl` binary.
4. A row is written to `licenses` (model `App\Models\LicenseFile`) with its licence (`token_id`),
   limits, features, `policy_id`, `policy_type` and `issued_by` (`user` for a download, `console`
   for an online activation). The file itself is not stored.

| Route | Auth | Notes |
|---|---|---|
| `POST /api/licenses/{id}/file` | Sanctum | body `install_id` (required or optional per policy; a UUID when given); `throttle:5,1` |
| `POST /dashboard/licences/{id}/file` | web session | same; errors come back on the page |
| `POST /api/licenses/activate` | licence key | with `"license_file": true` the answer carries `license_file` (base64 .atgl); without it, the 2.2 answer |

Refused with 403: unconfirmed, expired, or not yet approved licences. 409: the licence is in use by
another console. 409 on revoke: the licence is in use and not expired. 503 (or `license_file: null` with `license_file_error` on activate): the signing
keys are missing or malformed; the message gives no details.

**Licence limit.** A user may hold at most the policy's "Licence limit" licences (at least 1). The
dashboard has a "Need more licences" link (also shown at the limit) to `/contact?topic=more_licences`,
which selects "Need more licences" and fills in the subject "Need help to get more Licences". Only
an admin raises the limit (plan default or the user's custom license).

## Policies

**Admin → Access → License Policy** (`/admin/license-policy`, admins only, checked on the server)
edits the defaults for licences (keys and files alike):

| Field | Validation |
|---|---|
| Licence limit | integer >= 1: total licences a user may hold; users contact AtGlance Support to get more |
| Node limit | integer >= 0, `0` = unlimited |
| User limit | integer >= 0, `0` = unlimited |
| License valid up to | number + unit (months or years), 1 to 120 months in total |
| License file expiry window | 5 to 180 minutes (hard cap: the licence format allows at most 3 hours) |
| Installation ID for licence files | `required` or `optional` (default). Online key activation records it automatically |
| Free features (always included) | shown checked and greyed out; not saved, never in a licence, cannot be turned off |
| Premium features | checkboxes for the premium catalog keys: `nfs_storage` (NFS Storage: the console's NFS, backup, restore and migration plugin) and `premium` (Premium plan marker). Only these keys go into `features` |

There is one **global default** and one **default per plan of Admin → Pricing plans** (the plan
name as a slug is the plan code users carry, e.g. `Enterprise` → `enterprise`). A plan default
with "Use the global default" uses the global one. Saving affects only licences generated
afterwards; issued files never change. Every change is written to `policy_audit_log` (admin, field,
old and new value, time) and shown under the form.

**Resolution order** for a licence (key activation or file download): the user's custom license, then the plan default,
then the global default.

## Custom licenses

**Admin → Users → Edit → Create custom license** (button at the top of the user's edit page) opens
a form pre-filled from the policy that
applies to the user. The admin may change the licence limit, the node and user limits, the validity or an explicit expiry date, the
activation window (still 180 minutes at most), the Installation ID rule, the premium features, a fixed Installation ID and internal notes.
It is saved as a `custom_license_policies` row (`policy_type = custom`, `created_by_admin_id`).

Delivery: the policy is attached to the user, whose licences then use it, with the key or a
downloaded file. A file's activation window starts when the user downloads it, not while a file
is passed along. The dashboard shows "Standard" or "Custom", the limits and Premium Features.

The admin can edit or **revoke** the custom license from the same row. After revoking, new files
use the default policy again.

**Offline revocation.** Files already downloaded cannot be revoked: the console checks them offline
and they work until `expires_at`. Revoking only changes files generated afterwards.

All issued files are listed in **Admin → Access → Licence files** with filters for user, policy type,
plan and expiry.

## Features

**Admin → Access → Feature Catalog** lists every console feature with its **tier**:

- **free** (13 keys: systems and configs, users, workspaces, API keys, service accounts, SSO, AI Connect,
  MCP, custom domain, S3, backup & restore, notifications, activity log): always on in the console, never in
  a licence, shown checked and greyed out in policy forms;
- **premium** (`nfs_storage` "NFS Storage", `premium` "Premium Features"): toggled per policy and written
  into `features`.

The issuer writes only premium keys (from the resolved policy; anything a client sends is ignored). The
dashboard lists the premium features of the user's policy, and the pricing page shows a "Premium" badge next
to a plan feature named like a premium feature (e.g. "NFS Storage").

The keys and tiers come from the workspace `contracts/FEATURES.md` (copied to `resources/licensing/features.json`)
and cannot be created, renamed, re-tiered or deleted on the site; admins edit the display name and description (audited).
New keys from the contract appear after `php artisan migrate` or on opening the catalog page.

## Keys

```
php artisan license:generate-keys atgl-2026-10
```

It prints, and writes nothing to disk:

- `LICENSE_KEY_ID`, `LICENSE_SIGNING_PRIVATE_KEY` and `LICENSE_ENC_KEY` for this site's
  environment (secret manager or server `.env`; never the repo);
- the keyring entry (public key and AES key) for the console build.

Ship a console release that contains the new keyring entry **before** this site signs with the new
key ID. Then set the env values and run `php artisan config:cache`. Rotation and leaked-key steps:
see the console's `docs/LICENSING.md`.

`LICENSE_MIN_CONSOLE_VERSION` (default `2.3.0`) is written into each file as `min_version`.

## Tests

```
php vendor/bin/phpunit --filter LicenseFile
```

- `tests/Feature/LicenseFileTest.php` covers auth, account-only values, the Installation ID (required / optional), the in-use revoke lock, rate
  limits, the daily cap, missing keys, the dashboard form, and the activation-window list.
- `tests/Unit/LicenseFileContractTest.php` checks the issuer against the shared test vectors in
  `tests/fixtures/licensing/` (a copy of the workspace `contracts/fixtures/`; test keys only). Set
  `ATGLANCE_CONTRACTS_DIR` to the workspace `contracts/` folder to also check that the copy is
  current.
- Cross-repo end-to-end, from the workspace:
  `php contracts/tools/e2e.php <this-repo> <console-composer-dir>`.
