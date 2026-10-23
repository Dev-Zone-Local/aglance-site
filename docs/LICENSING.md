# Licensing: offline licence files (.atgl)

atglance.live issues licence files for the Management Console. The console verifies them offline
with built-in public keys and never calls atglance.live. The file format is defined in the AtGlance
workspace, `contracts/LICENSE_FORMAT.md`. The console repo's `docs/LICENSING.md` covers the console
side: verification, the read-only lock, and error codes.

The older online licence keys (`atg_...`, `/api/licenses/*`) remain in place for consoles up to 2.2.

## What the site does

1. A signed-in user opens **Dashboard > Licences** and clicks **Generate licence file**. The
   Installation ID field is optional.
2. `App\Support\LicenseFile\Issuer` builds the payload from the account and plan, never from the
   request:
   - `customer` is the account name, and `email` the account email;
   - `plan`, `seats`, `features` and the validity come from `config('atglance.plans')`;
   - `issued_at` is now, `activate_before` is now + 3 hours, `expires_at` is now + `valid_days`.
3. `PayloadBuilder` produces the exact JSON bytes. `Packer` signs them with Ed25519, encrypts with
   AES-256-GCM (random 12-byte IV, header as AAD), and packs the `.atgl` binary.
4. A row is written to `licenses` (model `App\Models\LicenseFile`). The file itself is not stored.
5. The response is `application/octet-stream` with
   `Content-Disposition: attachment; filename="atglance-<license_id>.atgl"`.

| Route | Auth | Limits |
|---|---|---|
| `POST /api/license/generate` | Sanctum | `throttle:5,1`, 20 files per user per 24 h |
| `POST /dashboard/licences/file` | web session | same |

Refused with 403: unverified email, and accounts flagged for admin approval. Refused with 503:
the keys are missing or malformed. The 503 message gives no details.

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
