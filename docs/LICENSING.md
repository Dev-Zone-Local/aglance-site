# Licensing: offline licence files (.atgl)

atglance.live issues licence files for the Management Console. The console verifies them offline
with built-in public keys and never calls atglance.live. The file format is defined in the AtGlance
workspace, `contracts/LICENSE_FORMAT.md`. The console repo's `docs/LICENSING.md` covers the console
side: verification, the read-only lock, and error codes.

The older online licence keys (`atg_...`, `/api/licenses/*`) remain in place for consoles up to 2.2.

## What the site does

1. A signed-in user opens **Dashboard > Licences** and clicks **Generate licence file**. The
   Installation ID field is optional.
2. `App\Support\LicenseFile\Issuer` builds a payload version 2 from the account and the resolved
   policy, never from the request:
   - `customer` is the account name, and `email` the account email;
   - `PolicyResolver` picks the policy: the user's custom license, then the user's plan default,
     then the global default (see "Policies");
   - the policy gives `max_nodes`, `max_users`, `features`, the validity (`expires_at`) and the
     activation window (`activate_before` = now + 5 to 180 minutes).
3. `PayloadBuilder` produces the exact JSON bytes. `Packer` signs them with Ed25519, encrypts with
   AES-256-GCM (random 12-byte IV, header as AAD), and packs the `.atgl` binary.
4. A row is written to `licenses` (model `App\Models\LicenseFile`) with the limits, features,
   `policy_id`, `policy_type` and `issued_by`. The file itself is not stored.
5. The response is `application/octet-stream` with
   `Content-Disposition: attachment; filename="atglance-<license_id>.atgl"`.

| Route | Auth | Limits |
|---|---|---|
| `POST /api/license/generate` | Sanctum | `throttle:5,1`, 20 files per user per 24 h |
| `POST /dashboard/licences/file` | web session | same |

Refused with 403: unverified email, and accounts flagged for admin approval. Refused with 503:
the keys are missing or malformed. The 503 message gives no details.

## Policies

**Admin → Access → License Policy** (`/admin/license-policy`, admins only, checked on the server)
edits the defaults for new licence files:

| Field | Validation |
|---|---|
| Node limit | integer >= 0, `0` = unlimited |
| User limit | integer >= 0, `0` = unlimited |
| License valid up to | number + unit (months or years), 1 to 120 months in total |
| License file expiry window | 5 to 180 minutes (hard cap: the licence format allows at most 3 hours) |
| Features enabled | catalog keys (Feature Catalog) |

There is one **global default** and one **default per plan** (`free`, `enterprise`). A plan default
with "Use the global default" uses the global one. Saving affects only licences generated
afterwards; issued files never change. Every change is written to `policy_audit_log` (admin, field,
old and new value, time) and shown under the form.

**Resolution order** when a user generates a file: the user's custom license, then the plan default,
then the global default.

## Custom licenses

**Admin → Users → Create custom license** (row action) opens a form pre-filled from the policy that
applies to the user. The admin may change the limits, the validity or an explicit expiry date, the
activation window (still 180 minutes at most), the features, an Installation ID and internal notes.
It is saved as a `custom_license_policies` row (`policy_type = custom`, `created_by_admin_id`).

Delivery: the policy is attached to the user, who generates the file from their own dashboard.
The activation window therefore starts when the user downloads the file, not while a file is passed
along. The dashboard shows "Standard" or "Custom", the limits and the features.

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
