# Contributing to NO Comments

Thanks for helping improve NO Comments. The project deliberately keeps a narrow scope: disable WordPress comments cleanly, preserve intentional WooCommerce reviews, and provide safe cleanup/automation tools.

## Before opening a change

Good contributions include:

- reproducible bugs;
- WordPress/PHP compatibility fixes;
- Multisite edge cases;
- WooCommerce review compatibility fixes;
- accessibility improvements;
- safer cleanup behavior;
- focused documentation or translation improvements.

Large feature expansions should start as an issue so the project does not accidentally become a general-purpose moderation suite.

## Requirements

- PHP 7.4+
- Composer 2
- WordPress 5.9+ compatibility target
- Docker Compose v2 for the optional local environment

CI currently exercises PHP 7.4, 8.0, 8.2, 8.3, 8.4 and 8.5 and runs the official WordPress Plugin Check action.

## Setup

```bash
composer install
```

Optional local WordPress stack:

```bash
cd dev
cp .env.example .env
docker compose up -d
```

See `dev/README.md` for the manual smoke-test checklist.

## Quality checks

```bash
# WordPress Coding Standards
composer lint

# Full PHPCS report
composer run lint:report

# Best-effort autofix
composer fix

# PHP syntax
find no-comments -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

A pull request should not be considered ready if CI is red unless the failure is clearly unrelated and documented.

## Safety rules for cleanup changes

Comment deletion is the highest-risk area of this plugin. Changes there should preserve these invariants:

1. Dry-run never mutates data.
2. `scope=trash` always means permanent deletion of already trashed comments.
3. `strategy=trash` for other scopes must remain reversible.
4. `scope=all + strategy=trash` must not empty Trash in the same operation.
5. Batches must terminate even if another plugin vetoes a comment mutation.
6. Filtering by post type must never broaden the requested scope.

If a change touches `DeleteService`, manually test these cases before opening a PR.

## WordPress behavior to verify

For changes to comment blocking, verify at least:

- a normal post with comments open;
- a normal post with comments already closed;
- a WooCommerce product with reviews open;
- a WooCommerce product with reviews closed;
- authenticated REST requests;
- XML-RPC behavior when enabled/disabled;
- Multisite with and without network `enforce`.

## Project structure

```text
no-comments/
  no-comments.php
  includes/
    Application/
      DeleteService.php
    Infrastructure/
      OptionsRepository.php
  languages/
  readme.txt
  uninstall.php
```

The distributable plugin is the `no-comments/` directory. Development tooling stays outside it.

## Versioning and releases

For a release:

1. Update the plugin header version in `no-comments/no-comments.php`.
2. Update `Stable tag` and the changelog in `no-comments/readme.txt`.
3. Update the version badge/release notes in the root README when necessary.
4. Ensure CI passes.
5. Tag the merge commit as `vX.Y.Z`.

A `v*` tag triggers the release workflow, which creates a clean plugin ZIP and attaches it to a GitHub Release. Do not commit generated ZIP files to source control.

## Pull requests

Keep PRs focused. In the description include:

- what changed;
- why it changed;
- user impact;
- risk/rollback considerations for destructive behavior;
- how you validated it.

## Security

Do not publish vulnerability details in a normal issue. Follow `SECURITY.md` for private reporting guidance.
