# Licensing: Management Console licence files

Management Console licences are **licence files** (`.atgl`). Licence keys are no longer issued and
the console licence-key API (`/api/licenses/*`) is removed; consoles up to 2.2 that relied on it
must move to a licence file.

A signed-in user generates a file on **Dashboard > Licences** and uploads it in the console
installer or in Admin Settings › Licence. The console checks it offline with built-in public keys
and never calls atglance.live. The file format is defined in the AtGlance workspace,
`contracts/LICENSE_FORMAT.md`; the console repo's `docs/LICENSING.md` covers the console side.

## What the site does

1. `App\Support\LicenseFile\Issuer` resolves the user's policy (`PolicyResolver`: custom license,
   then plan default, then global default) and checks it:
   - **Licence file limit**: the user's files that are not expired (and not released by an admin)
     must be below the limit. A file counts until it expires because it cannot be revoked
     offline, so deleting or revoking anything never frees a slot. At the limit the user is told to
     contact the AtGlance Support team.
   - **Email verification** (on/off): the 5-digit code emailed to the account must be entered.
   - **Installation ID** (required/optional): with one, the file works on that console only.
   - Accounts with "Block licence file generation" (Users › Edit) cannot generate files.
2. The policy gives `max_nodes`, `max_users`, `features`, the validity (`expires_at`) and the
   activation window (`activate_before` = now + 5 to 180 minutes).
3. `PayloadBuilder` produces the exact JSON bytes. `Packer` signs them with Ed25519, encrypts with
   AES-256-GCM (random 12-byte IV, header as AAD), and packs the `.atgl` binary.
4. A row is written to `licenses` (model `App\Models\LicenseFile`). The file itself is not stored.

| Route | Auth | Notes |
|---|---|---|
| `POST /api/license-files/code` | Sanctum | emails the 5-digit code; `throttle:3,1` |
| `POST /api/license-files` | Sanctum | body `install_id`, `code`; downloads the file; `throttle:5,1` |
| `POST /dashboard/licences/file` | web session | same as above; errors come back on the page |

Refused with 403: limit reached, blocked account. 422: Installation ID or code. 503: the signing
keys are missing or malformed (no details in the message).

**Releasing a slot.** In **Admin → Access → Licence files**, "Release slot" stops an active file
from counting toward the user's limit (e.g. after a confirmed hardware move). The file itself keeps
working offline until it expires. Releases are written to `policy_audit_log`.

## Policies

**Admin → Access → License Policy** (`/admin/license-policy`, admins only, checked on the server)
edits the defaults for licence files:

| Field | Validation |
|---|---|
| Licence file limit | integer >= 1: files a user may hold until they expire; users contact AtGlance Support to get more |
| Email verification | on/off: a code emailed to the account is needed to generate a file |
| Installation ID | required or optional when a file is generated |
| Node limit | integer >= 0, `0` = unlimited |
| User limit | integer >= 0, `0` = unlimited |
| License valid up to | number + unit (months or years), 1 to 120 months in total |
| License file expiry window | 5 to 180 minutes (hard cap: the licence format allows at most 3 hours) |
| Features enabled | catalog keys (Feature Catalog) |

There is one **global default** and one **default per plan of Admin → Pricing plans** (the plan
name as a slug is the plan code users carry, e.g. `Enterprise` → `enterprise`). A plan default
with "Use the global default" uses the global one. Saving affects only licences generated
afterwards; issued files never change. Every change is written to `policy_audit_log` (admin, field,
old and new value, time) and shown under the form.

**Resolution order** when a file is generated: the user's custom license, then the plan default,
then the global default.

## Custom licenses

**Admin → Users → Edit → Create custom license** (button at the top of the user's edit page) opens
a form pre-filled from the policy that
applies to the user. The admin may change the licence file limit, email verification, the
Installation ID rule, the node and user limits, the validity or an explicit expiry date, the
activation window (still 180 minutes at most), the features, an Installation ID and internal notes.
It is saved as a `custom_license_policies` row (`policy_type = custom`, `created_by_admin_id`).

Delivery: the policy is attached to the user, who generates the files from their own dashboard.
A file's activation window starts when the user downloads it, not while a file is passed along. The dashboard shows "Standard" or "Custom", the limits and the features.

The admin can edit or **revoke** the custom license from the same row. After revoking, new files
use the default policy again.

**Offline revocation.** Files already downloaded cannot be revoked: the console checks them offline
and they work until `expires_at`. Revoking only changes files generated afterwards.

All issued files are listed in **Admin → Access → Licence files** with filters for user, policy type,
plan and expiry.

## Features

**Admin → Access → Feature Catalog** lists the features a licence can enable. The keys come from
the workspace `contracts/FEATURES.md` (copied to `resources/licensing/features.json`) and cannot
be created, renamed or deleted on the site; admins edit the display name and description (audited).
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

- `tests/Feature/LicenseFileTest.php` covers auth, account-only values, the Installation ID, rate
  limits, the daily cap, missing keys, the dashboard form, and the activation-window list.
- `tests/Unit/LicenseFileContractTest.php` checks the issuer against the shared test vectors in
  `tests/fixtures/licensing/` (a copy of the workspace `contracts/fixtures/`; test keys only). Set
  `ATGLANCE_CONTRACTS_DIR` to the workspace `contracts/` folder to also check that the copy is
  current.
- Cross-repo end-to-end, from the workspace:
  `php contracts/tools/e2e.php <this-repo> <console-composer-dir>`.
