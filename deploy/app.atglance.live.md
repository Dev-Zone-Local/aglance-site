# Merging app.atglance.live into atglance.live

app.atglance.live used to be a separate static downloads site (`/www/wwwroot/app.atglance.live`).
It is now served by the same Laravel app as atglance.live:

- the download files are stored once, on the `downloads` disk (`storage/app/downloads`, or
  `DOWNLOADS_PATH`), and are managed under **Admin → Downloads**;
- every old file URL keeps working on app.atglance.live (for example
  `/cli/atglance_2.2/atglance_2.2.0_amd64.deb`, `/console/atglance-installer.sh`, `/console/Old-V1/...`);
- `/`, `/cli/` and `/console/` on app.atglance.live redirect (301) to `https://atglance.live/downloads`;
- `/console/atglance-updater.sh` and `.ps1`, which the old page linked by mistake, now serve
  `atglance_update.sh` and `.ps1`;
- `SHA256SUMS` in any old folder is built from the stored files.

New files you upload in the admin panel appear on atglance.live (`/downloads`, `/cli`, `/console`,
release notes, the dashboard installer, `/api/downloads`) and at their app.atglance.live paths:
CLI files at `cli/atglance_<major>.<minor>/<file>`, console scripts at `console/<file>`.

## One-time steps on the production server

Do them in this order. Until step 3 the old static site keeps answering.

1. Deploy this branch to atglance.live as usual (`bash /www/deploy/atglance.live.sh`). The
   deploy runs the migration that creates the `download_files` table.

2. Import the old files (they keep their old paths):

   ```bash
   cd /www/wwwroot/atglance.live
   sudo -u www /www/server/php/85/bin/php artisan downloads:import-legacy /www/wwwroot/app.atglance.live --dry-run
   sudo -u www /www/server/php/85/bin/php artisan downloads:import-legacy /www/wwwroot/app.atglance.live
   ```

   Console scripts are attached to the latest console release. Use `--console-version=2.3.0` to
   choose another one.

3. Point app.atglance.live at the Laravel app. In aaPanel, **Website → app.atglance.live → Site
   directory**, set the root to `/www/wwwroot/atglance.live/public`, and give the site the same
   PHP and URL rewrite as atglance.live:

   ```nginx
   root /www/wwwroot/atglance.live/public;
   index index.php index.html;
   include enable-php-85-atglance.conf;   # same PHP include as atglance.live

   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

   Keep the HTTPS certificate of app.atglance.live. aaPanel keeps answering the
   `/.well-known/acme-challenge/` requests for renewals.

   Then `nginx -t && nginx -s reload`.

4. Check:

   ```bash
   curl -sI https://app.atglance.live/ | grep -i location          # https://atglance.live/downloads
   curl -fsSL https://app.atglance.live/console/atglance-installer.sh | head -3
   curl -fsSLO https://app.atglance.live/cli/atglance_2.2/SHA256SUMS && cat SHA256SUMS
   ```

5. When everything works, archive the old folder (`/www/wwwroot/app.atglance.live`). It is no
   longer read.

## Settings

| `.env` key | Default | What it does |
|---|---|---|
| `LEGACY_DOWNLOAD_HOST` | `app.atglance.live` | Host that answers with the old paths. Empty turns it off. Locally: `app.atglance.localhost` |
| `DOWNLOADS_PATH` | `storage/app/downloads` | Where release files are stored |
| `DOWNLOAD_UPLOAD_MAX_KB` | `204800` | Largest upload in the admin panel. PHP `upload_max_filesize` and `post_max_size` must allow it |
