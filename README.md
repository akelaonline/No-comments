# NO Comments

**Disable WordPress comments completely — without breaking WooCommerce reviews when you still need them.**

[![Quality](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml/badge.svg)](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml)
![Version](https://img.shields.io/badge/version-1.14.0-111827)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.5-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-16a34a)

NO Comments is a focused WordPress utility for sites that do not need public discussion. One switch closes comments and pings site-wide; optional hardening removes comment entry points from REST/XML-RPC; cleanup tools let administrators safely inspect, trash, or permanently delete existing comments.

It is intentionally small, self-hosted and telemetry-free.

## Why this exists

Turning off “Allow people to submit comments” in WordPress is not always the end of the story. Existing content can retain comment settings, comment-related admin UI remains visible, APIs may still expose comment routes, and old spam can keep accumulating operational clutter.

NO Comments gives that job one dedicated place:

- close comments and pings globally;
- keep comments open on selected post types (exceptions, incl. WooCommerce reviews);
- auto-close comments on content older than N days without disabling the site;
- auto-delete spam on a schedule (WP-Cron, daily/twice-daily/weekly);
- hide comment-management UI when it is no longer useful;
- optionally remove comment REST endpoints and XML-RPC comment creation;
- safely clean old comments with dry-runs and scoped deletion;
- enforce one policy across WordPress Multisite;
- automate administration through WP-CLI or authenticated REST requests;
- export/import settings as JSON to back up or clone configuration;
- short-circuit comment queries and comment feeds when the shutdown is active;
- purge page cache for affected posts after bulk deletion (Tucho-ready).

## Features

### Global comment shutdown

- Closes comments across public post types.
- Closes pings/trackbacks.
- Removes comment support from public post types while enabled.
- Hides the Comments menu/admin-bar item where appropriate.
- Redirects direct access to comment/discussion screens.
- Exposes the current state through WordPress Site Health.
- **Zero-cost frontend:** while the shutdown is active, comment queries are short-circuited through `comments_pre_query` (no database queries) and comment feeds are disabled (discovery link removed, direct access redirected home).

### Post-type exceptions

Keep comments (and pings) on selected post types while the global shutdown is active. The Comments menu, queries and submission flow keep working for those types. WooCommerce `product` is automatically an exception when **Keep product reviews** is enabled, and everything honors each product's own review state.

### Auto-close by age

Set a number of days and NO Comments closes forms/pings on content older than that — useful for sites that still want comments on recent posts but not on old ones. Applies when the global shutdown is off (0 = disabled). Existing comments stay visible; only new submissions are blocked.

### Scheduled spam cleanup

Enable **Auto cleanup** and NO Comments deletes spam on a WP-Cron schedule (daily, twice-daily or weekly). Every run is recorded (timestamp + count), runs are guarded against concurrency, and you can trigger or inspect it from WP-CLI (`wp no-comments cleanup status|run`).

### Cache purge after bulk deletion

After a real bulk delete, the affected post IDs are published through the `no_comments_after_delete` action, and Tucho page cache is purged per post when Tucho is active (`tucho_purge_post`).

### API hardening

When enabled, you can independently:

- remove the core `wp/v2/comments` REST routes;
- remove the XML-RPC `wp.newComment` method.

NO Comments also rejects new comment insertion using WordPress' `pre_comment_approved` flow, which supports a proper `WP_Error` response.

### Safe cleanup

The **Delete Comments** screen supports:

- Spam;
- Pending;
- Trash;
- All comments;
- optional post-type filters;
- dry-run counting before mutation;
- permanent deletion;
- reversible movement to Trash;
- an explicit `DELETE` confirmation before destructive execution.

A key safety rule in 1.11.0: **All + Move to Trash does not empty Trash in the same operation.** Existing or newly trashed comments remain recoverable until you explicitly empty Trash.

### WooCommerce-aware

Enable **Keep product reviews** to leave product reviews available while comments stay disabled everywhere else.

The plugin preserves each product's own review state: it will not force-open a product whose reviews were intentionally closed.

The compatibility option can also be configured before WooCommerce is activated.

### Settings transfer

Export the full configuration (site + network when applicable) to a JSON file and import it elsewhere — handy for backups and for cloning the same policy across client sites. Available from the settings screen (**Import / Export settings**), the REST API (`/settings/export`, `/settings/import`) and WP-CLI (`wp no-comments settings export|import`).

### Multisite

Network administrators can define:

- global comment state;
- REST blocking;
- XML-RPC blocking;
- WooCommerce review compatibility;
- `enforce` mode across all sites.

When network enforcement is active, site-level settings are treated as read-only and the administrative REST endpoint rejects conflicting site-level writes.

## Requirements

| Component | Requirement |
|---|---|
| WordPress | 6.0+ |
| Tested with | WordPress 7.0.x |
| PHP | 7.4+ |
| CI matrix | PHP 7.4, 8.0, 8.2, 8.3, 8.4, 8.5 |
| License | GPL-2.0-or-later |

The included Docker development environment targets WordPress 7.0.2 on PHP 8.3.

## Installation

### From a release ZIP

1. Download the release ZIP.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload and activate it.
4. Open **Settings → NO Comments**.

### From source

Copy the `no-comments/` directory into:

```text
wp-content/plugins/no-comments/
```

Then activate **NO Comments** from WordPress Admin.

## WP-CLI

```bash
# Current state (effective, network-aware)
wp no-comments status

# Enable / disable global blocking
wp no-comments enable
wp no-comments disable

# Always inspect first
wp no-comments delete --scope=spam --dry-run

# Delete comments for selected post types (strategy: delete|trash)
wp no-comments delete --scope=all --types=post,page --strategy=delete

# WooCommerce review compatibility
wp no-comments woo-reviews on
wp no-comments woo-reviews off
wp no-comments woo-reviews status

# Export / import settings
wp no-comments settings export --file=no-comments.json
wp no-comments settings import no-comments.json

# Post-type exceptions
wp no-comments exceptions list
wp no-comments exceptions add page
wp no-comments exceptions remove page

# Auto-close by age (days)
wp no-comments auto-close 30
wp no-comments auto-close status

# Scheduled spam cleanup
wp no-comments cleanup status
wp no-comments cleanup enable --interval=weekly
wp no-comments cleanup run
wp no-comments cleanup disable
```

## REST API

Administrative endpoints:

```text
GET  /wp-json/no-comments/v1/settings
POST /wp-json/no-comments/v1/settings
POST /wp-json/no-comments/v1/actions/delete
GET  /wp-json/no-comments/v1/settings/export
POST /wp-json/no-comments/v1/settings/import
```

Settings payload fields:

```json
{
  "level": "site",
  "enabled": true,
  "rest": true,
  "xmlrpc": true,
  "woo": false,
  "enforce": false,
  "exceptions": ["page"],
  "auto_close_days": 30,
  "auto_cleanup": true,
  "auto_cleanup_interval": "weekly"
}
```

Delete payload example:

```json
{
  "scope": "spam",
  "types": ["post", "page"],
  "strategy": "delete",
  "dry_run": true
}
```

Import payload example (only whitelisted keys are applied):

```json
{
  "level": "site",
  "settings": {
    "enabled": true,
    "rest": true,
    "xmlrpc": true,
    "woo": false
  }
}
```

Endpoints require the corresponding WordPress administrator capabilities. For remote automation, use WordPress Application Passwords over HTTPS or another supported authenticated WordPress flow.

## Privacy

NO Comments does **not**:

- collect telemetry;
- create an external account;
- call a SaaS backend;
- send comment content off-site.

All settings and cleanup operations stay inside your WordPress installation.

## Development

Install development dependencies:

```bash
composer install
```

Run code quality checks:

```bash
composer lint
composer run lint:report
composer fix
```

GitHub Actions validates:

- Composer metadata;
- PHP syntax;
- WordPress Coding Standards;
- PHP 7.4 through 8.5;
- the official WordPress Plugin Check action.

For a disposable WordPress environment:

```bash
cd dev
cp .env.example .env
docker compose up -d
```

See [`dev/README.md`](dev/README.md) for the smoke-test checklist.

## Repository structure

```text
.
├── .github/workflows/      # CI and release automation
├── dev/                    # local WordPress Docker environment
├── no-comments/            # distributable plugin
│   ├── includes/
│   │   ├── Application/
│   │   └── Infrastructure/
│   ├── languages/
│   ├── no-comments.php
│   ├── readme.txt
│   └── uninstall.php
├── composer.json
├── phpcs.xml
├── CONTRIBUTING.md
├── SECURITY.md
└── LICENSE
```

## Releases

Source code stays source-only: release ZIPs are not committed to the repository.

Tags matching `v*` trigger the release workflow, which packages only the distributable `no-comments/` directory and creates a GitHub Release with the ZIP attached.

## Contributing

Bug reports, compatibility findings and focused pull requests are welcome. Please read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a PR.

For security issues, do not publish exploit details in a normal issue. Follow [`SECURITY.md`](SECURITY.md).

## Author

Created and maintained by **Akela** ([`@akelaonline`](https://github.com/akelaonline)).

WordPress plugins in the Akela ecosystem: **Akela SEO** and **Tucho Performance**.

- GitHub: [akelaonline](https://github.com/akelaonline)
- Instagram: [@akelaonline](https://www.instagram.com/akelaonline/)

## License

NO Comments is free software licensed under **GPL-2.0-or-later**. See [`LICENSE`](LICENSE).
