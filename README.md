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
