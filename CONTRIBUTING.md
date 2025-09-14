# Contributing – NO Comments (WordPress)

Gracias por contribuir. Este repositorio busca mantener un alto estándar de calidad, seguridad y DX.

## Requisitos

- PHP 7.4+ (probado también en 8.0 y 8.2)
- Composer
- WordPress 6.6+
- Docker (opcional, carpeta `dev/` ya provista)

## Setup local

```bash
composer install
```

Si usas Docker, en `dev/`:

```bash
chmod +x scripts/wp-setup.sh
docker compose up -d
```

## Linter (PHPCS + WPCS + PSR-12)

- Reglas: `phpcs.xml`
- Ejecutar:

```bash
composer lint
# o con reporte completo
composer run lint:report
# autofix (best effort)
composer fix
```

## Tests (próximo sprint)

Se integrará WordPress test suite (unit + integration) y GitHub Actions.

## Estructura del plugin

```text
no-comments/
  no-comments.php              # Bootstrap y orquestación
  includes/
    Application/
      DeleteService.php        # Lógica de conteo/borrado (en refactor)
  languages/
  uninstall.php
```

## Empaquetado

Desde la raíz del workspace (`/Users/…/no comments`):

```bash
zip -r "no-comments-<version>.zip" "no-comments" -x "no-comments/.DS_Store" "no-comments/.git/*" "no-comments/node_modules/*" "no-comments/dev/*"
```

Ejemplo actual:

```bash
zip -r "no-comments-1.10.0.zip" "no-comments" -x "no-comments/.DS_Store" "no-comments/.git/*" "no-comments/node_modules/*" "no-comments/dev/*"
```

## Publicación

- Actualiza `Stable tag` y `Changelog` en `no-comments/readme.txt`.
- Asegúrate de que el header `Version` en `no-comments/no-comments.php` coincida.
- Genera el ZIP limpio y súbelo al WP donde se quiera instalar.

## WP-CLI útil

```bash
# Estado
wp no-comments status

# Activar/desactivar
wp no-comments enable
wp no-comments disable

# Borrar
wp no-comments delete --scope=spam --dry-run
wp no-comments delete --scope=all --types=post,page

# Woo reviews (compat)
wp no-comments woo-reviews on|off|status
```

## REST API (nivel admin)

- `GET  /wp-json/no-comments/v1/settings`
- `POST /wp-json/no-comments/v1/settings` (body JSON: `level`, `enabled`, `rest`, `xmlrpc`, `woo`, `enforce`)
- `POST /wp-json/no-comments/v1/actions/delete` (body JSON: `scope`, `types`, `strategy`, `dry_run`)

Proteger siempre con Nonces/Cookies de admin (o usar credenciales de aplicación).
