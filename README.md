# TCUK All In One Migrator

Concise, practical migration and backup plugin for WordPress. Supports zip backups, token-auth API Push, GitHub theme pulls and SSH transfers.

Features

- Zip backups: create, download, upload and restore selective components (theme, plugins, uploads, database, mu-plugins).
- API Push: token-authenticated REST endpoint to push/move backups between sites (includes endpoint probe and fallback variants).
- SSH Transfers: optional SSH-based remote backups and restores with remote directory probing and key management.
- GitHub theme pull: install a theme from `owner/repo` (branch and subdirectory supported).
- Safe DB handling: serialized/JSON-aware search-and-replace plus table-prefix remapping during restores.
- Admin UX: AJAX-first flows, toast notifications, and responsive two-column SSH key layout.

Installation

1. Upload `tcuk-all-in-one-migrator` to `wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin and open **TCUK Migrator** in the admin menu.

Quick Start

1. Configure **Connection Settings** (API Push destination/receive, SSH if used, and GitHub repo if required).
2. Run the **Setup Wizard** to validate environment and endpoint connectivity.
3. Make a small test backup (theme-only) and run a restore on a staging site.

Premium & Purchase

Purchase premium features, view documentation, and get priority support at:

https://aiomigrator.co.uk

Notes & Safety

- Always create and download a backup before any destructive action.
- Only run restores/migrations with administrator access.
- Large restores may require increased PHP memory/time or staging-based runs.

Troubleshooting (high level)

- API Push 404: ensure the receive endpoint is enabled on destination and flush permalinks.
- API Push 403: enable the receive endpoint and use the destination receive token as the source API Push Key.
- API Push 500: check `wp-content/debug.log` and server PHP logs; try a smaller component set to isolate the error.

Changelog

- v1.2.0 — UI & release
  - AJAX-first admin flows and toast notifications
  - Remote directory probing and remote-backup select improvements
  - Responsive layout fixes and SSH/UI polishing

Development & Updates

- Plugin updates use GitHub Releases. Keep the folder name `tcuk-all-in-one-migrator` for auto-update compatibility.

Support

- Premium sales, documentation and priority support: https://aiomigrator.co.uk
- Report bugs or request features via the GitHub repo.

License

This plugin is licensed under the GNU General Public License v2.0 or later.

Version

`1.2.0`
# TCUK All In One Migrator

Powerful WordPress migration, backup and restore plugin with multiple transfer options (local upload, API push, GitHub theme pull, SSH Transfer).

Key capabilities:

- Create and restore zip backups (selective components: theme, plugins, uploads, database, mu-plugins)
- One-click API Push (token-authenticated REST receive endpoint) for automated local→live & live→live transfers
- GitHub theme pull (owner/repo, branch, optional subdirectory)
- Granular DB selection and safe serialized/JSON-aware search/replace during restores
- Admin UI improvements: AJAX-first flows, toast notifications, remote directory probing, responsive SSH key layout

## Installation

1. Copy the plugin folder to `wp-content/plugins/tcuk-all-in-one-migrator` OR Add plugin in the Wordpress Plugins screen.
2. Activate **TCUK All In One Migrator** from the WordPress Plugins screen.
3. Open the plugin from the admin menu: **TCUK Migrator**.

## Quick Start

1. Fill out **Connection Settings** (API Push destination/receive settings, GitHub repo if used).
2. Run the **Setup Wizard** to validate environment and endpoint connectivity.
3. Make a test backup (theme-only is a good start) and test restore on a staging environment.

## Notable Admin UX Improvements (v1.2.0+)

- AJAX-first admin flows that avoid full page reloads for a faster UX.
- Global toast helper for success/error/info notifications.
- Remote directory probing for SSH/remote backup selections (client probes candidates; server value remains authoritative).
- Two-column SSH key layout on wide screens; stacks on small screens.

## Premium & Purchase

For premium features, licensing and support visit: https://aiomigrator.co.uk

- Purchase premium, view documentation, and get priority support at the site above.

## Usage Notes & Safety

- Always create and download a backup before performing restores or destructive actions.
- Use admin-only accounts when running migrations.
- Large restores may hit host timeouts; test on staging and increase PHP memory/time if needed.

## Troubleshooting (short)

- API Push 404: ensure the destination plugin is active and the receive endpoint is enabled; flush permalinks (Settings → Permalinks → Save).
- API Push 403: enable the receive endpoint and copy the destination receive token to source settings.
- API Push 500: check `wp-content/debug.log` and server PHP logs; try smaller component restores to isolate the failure.

## Changelog

- v1.2.0 — UI & release
  - AJAX-first admin flows and toast notifications
  - Remote directory probing and remote-backup select improvements
  - Responsive layout fixes (two-column SSH keys, Free-mode layout)
  - Various semantic HTML fixes and polishing

## Development & Updates

- The plugin uses GitHub Releases for update packages. Keep the plugin folder name as `tcuk-all-in-one-migrator` for auto-update compatibility.

## Support & Contact

- Premium sales, documentation and priority support: https://aiomigrator.co.uk
- Open an issue or PR on the GitHub repo for bugs and feature requests.

## License

This plugin is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).

- License URI: https://www.gnu.org/licenses/gpl-2.0.html
- Copyright: TCUK

## Version

`1.2.0`

## Features

### 1) GitHub Theme Pull

- Repository: `owner/repo` or GitHub URL
- Branch selection
- Optional token for private repos / API limits
- Optional repo subdirectory (if theme is nested in a monorepo)
- Installs directly into: `wp-content/themes/<theme-slug>`

### 2) Setup Wizard

- One-click preflight check from admin dashboard
- Validates:
   - PHP/runtime capability checks
   - Backup storage writability
   - Destination URL sanity check
   - API Push endpoint + key probe (when configured)
- Wizard card is closable in the UI and can be reopened with **Show Setup Wizard**

### 3) Backups

- Create zip backups with selected components
- Store backups under: `wp-content/uploads/tcuk-migrator-backups`
- Uploads backups automatically exclude `tcuk-migrator-temp` and `tcuk-migrator-backups`
- Download backup files from admin
- Upload backup files from local/Mac directly in admin
- Restore selected components from a backup zip
- Delete old backups

### Simple transfer workflow

If you want a straightforward manual transfer:

1. On local (Mac), create a backup zip from plugin backup screen.
2. Download the backup zip.
3. On server/CyberPanel site, open plugin backup screen.
4. Upload the zip in **Upload Backup Zip (from Mac/local)**.
5. Restore selected components from the uploaded file.

### One-click API Push workflow (recommended)

1. On destination/live site:
   - Enable **API Push receive endpoint**
   - Save settings (token auto-generates if blank)
   - Copy the receive token
2. On source/local site:
   - Set **API Push Destination URL** (destination site URL)
   - Set **API Push Key** (token from destination)
   - Save settings
3. Use **API Push** action and select components
4. Plugin creates backup payload and pushes to destination endpoint
5. Destination auto-restores selected components
6. Run **Setup Wizard** on source site to confirm endpoint/token probe passes before first full push

Endpoint format:

- `https://your-site.com/wp-json/tcuk-migrator/v1/receive-backup`

Fallback endpoint format (for environments where rewrite/permalinks do not expose `/wp-json`):

- `https://your-site.com/?rest_route=/tcuk-migrator/v1/receive-backup`


## GitHub-Based Plugin Updates (Auto Update Alerts)

This plugin now checks GitHub Releases for new versions.

### One-time setup

1. Keep this plugin installed as folder: `tcuk-all-in-one-migrator`.
2. Use the plugin **Connection Settings → GitHub → Token / PAT** with a token that can read the repo.
   - Private repo: use a classic token with `repo` scope (or fine-grained read-only access to this repo).
   - Public repo: token optional but recommended to avoid API rate limits.
3. Save settings.

### How update alerts work

- WordPress checks GitHub latest release.
- If release tag version is higher than current plugin version, WordPress shows update notice in Plugins screen.
- The update package is downloaded from your release `.zip` asset (preferred).

### Release process (every update)

1. Update plugin version in `tcuk-all-in-one-migrator.php`:
   - Header `Version:`
   - `TCUK_MIGRATOR_VERSION` constant
2. Commit and push to `main`.
3. On push to `main`, GitHub Actions compares plugin version to the latest GitHub release:
   - If version increased, it auto-builds and publishes a new release ZIP asset.
   - If version is unchanged (or lower), it skips release creation.
4. In WordPress admin, click **Dashboard → Updates → Check Again**.
5. Go to **Plugins** and update when the new version appears.

### Important packaging rule

Do not rely only on GitHub “Source code (zip)” archives for plugin updates.
Use a release asset zip that contains the correct plugin root folder name (`tcuk-all-in-one-migrator`).

## Recommended First Setup

1. Fill **Connection Settings**:
   - Remote API receive + push settings
   - GitHub repo/branch/theme slug
2. Save settings.
3. Run **Setup Wizard** and resolve any fail/warning items.
   - If API Push is configured, wizard performs a non-destructive authenticated probe to the destination endpoint.
4. Run **Test API Push Connection** before first API push.
5. Run a **backup** before first migration.
6. Test with a limited component set first (example: theme only).
7. If Site Editor/footer patterns look incorrect after import, run **Run FSE Repair** from plugin settings.

## Operational Notes

- This plugin executes direct filesystem and SQL operations. Use with admin-only access.
- For DB migrations across environments, table prefix remapping is attempted automatically.
- After DB restore, `siteurl` and `home` are forced to the current destination domain to prevent accidental redirects back to source/local URLs.
- After DB restore, source URLs from backup manifest are search/replaced across destination DB (serialized and JSON-safe) to align content/media/admin behavior.
- After restore, plugin runs stabilization steps: sync active theme from manifest (if present), flush rewrite rules, and flush cache to reduce Site Editor/REST inconsistencies.
- After restore, block-template post bindings are repaired to the active theme slug when needed (helps resolve FSE “item doesn't exist” and invalid JSON save issues after cross-site imports).
- Large sites can take time; operations run in request context, so host timeouts may apply.
- API Push requires the destination site endpoint to be enabled and reachable at `/wp-json/tcuk-migrator/v1/receive-backup`.
- Backup extraction uses direct `ZipArchive` when available, so API Push restore does not depend on interactive WordPress filesystem credential prompts.
- The plugin automatically retries API Push/probe using both endpoint variants (`/wp-json/...` and `?rest_route=...`) to handle rewrite/LiteSpeed 404 cases.

## Troubleshooting API Push 404

- Confirm destination plugin is active and **API Push receive endpoint** is enabled.
- In destination WP Admin, go to Settings → Permalinks and click **Save Changes** once to refresh rewrite rules.
- Test both endpoint variants in browser or `curl`; a valid response is typically JSON (401/403/200 is fine), not server HTML 404.
- If a CDN/WAF is in front of the site, allow requests to `/wp-json/*` and query-string `rest_route` requests.

## Troubleshooting API Push HTTP 500

- A 500 means the request reached destination, but destination failed during receive/restore.
- Check destination logs first:
   - `wp-content/debug.log` (enable `WP_DEBUG_LOG`)
   - Web server/PHP error log in your hosting panel
- Retry with a smaller component set (for example **Theme only**) to isolate failing component.
- Confirm destination has write access to `wp-content/uploads`, `wp-content/plugins`, and `wp-content/themes`.
- Ensure available PHP memory and execution time are sufficient for large plugin/uploads restores.

## Troubleshooting FSE Footer/Pattern Issues

- Run **Run FSE Repair** in plugin settings.
- This re-aligns template bindings/theme attributes and `wp_theme` taxonomy assignments to the active theme, normalizes URL scheme (`http`/`https`) for current host, and flushes rewrite/cache.
- It also cleans leaked placeholder tokens like `{<64-char-hash>}` back to `%` (fixes broken style values such as `flex-basis:44{...}`).

## Troubleshooting API Push HTTP 403 (Endpoint Disabled)

- Error like `Remote API is disabled on this site` means destination receive endpoint is currently off.
- On destination site, open plugin settings and enable **API Push receive endpoint**, then save.
- Use the destination **Receive API Token** as the source **API Push Key**.
- DB restores now preserve destination API receive settings (`remote_api_enabled` + token) to prevent this from being overwritten by source data.

## Safety Checklist

- Always create and download a backup before destructive operations.
- Confirm source/target theme slug carefully.
- Confirm plugin selection in selected mode.
- Use DB selected mode for partial migrations where possible.

## Version

`1.0.0`

## License

This plugin is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).

- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html
- Copyright: TCUK
