# NO Comments — local development

Docker Compose environment for developing and manually testing the plugin against the current stable WordPress stack.

## Stack

- WordPress 7.0.2
- PHP 8.3
- MariaDB 11.4
- WP-CLI
- phpMyAdmin

Both web ports bind to `127.0.0.1` by default, so this environment is intended for local development only.

## Requirements

- Docker Desktop or another Docker Engine with Compose v2

## Quick start

From `dev/`:

```bash
cp .env.example .env
docker compose up -d
```

The `wpcli` service waits for WordPress and the database, installs WordPress when needed, activates `no-comments`, enables the global comment block, and configures pretty permalinks.

Check setup progress:

```bash
docker compose logs wpcli
```

Default local endpoints:

- WordPress: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

Change ports and local development credentials in `.env` before starting the stack.

## Useful commands

```bash
# Service status
docker compose ps

# WordPress logs
docker compose logs -f wordpress

# Plugin status through WP-CLI
docker compose run --rm wpcli wp no-comments status

# WordPress plugin list
docker compose run --rm wpcli wp plugin list

# Stop services
docker compose down

# Stop and remove local volumes
docker compose down -v
```

## Manual smoke test

After startup, verify at least these flows:

1. Open **Settings → NO Comments** and enable/disable the global block.
2. Confirm normal post comments close when the plugin is enabled.
3. If WooCommerce is installed, enable **Keep product reviews** and verify product review state is preserved.
4. In **Delete Comments**, run a dry-run first.
5. Verify **All + Move to Trash** leaves comments in Trash and does not permanently delete them.
6. Verify **Empty Trash** permanently deletes trashed comments.
7. Exercise the REST settings endpoint with an authenticated administrator.
8. On Multisite, verify network enforcement prevents site-level changes.

## Notes

- `../no-comments` is bind-mounted directly into the WordPress plugins directory, so PHP changes are immediately visible.
- Database and WordPress files live in Docker volumes (`db_data`, `wp_data`).
- The sample credentials are intentionally development-only. Do not expose this stack to the public internet.
