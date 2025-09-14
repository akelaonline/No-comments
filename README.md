# NO Comments (WordPress)

[![CI](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml/badge.svg)](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml)
![WordPress](https://img.shields.io/badge/WordPress-6.6-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777bb3)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

Plugin minimalista para cerrar TODOS los comentarios y pings del sitio, con herramientas seguras para borrar comentarios (spam / pendientes / papelera / todos). Incluye WP‑CLI, REST API, compatibilidad WooCommerce y soporte Multisite con ajustes de red.

> Autor: [MKT Marketing Digital](https://mktmarketingdigital.com)

## Características clave

- Cierre global de comentarios y pings en todo el sitio.
- Opcional: bloquear endpoint REST de comentarios y XML‑RPC `wp.newComment`.
- Compatibilidad WooCommerce: mantener reseñas de productos (incluso con cierre global).
- Borrado masivo: alcances Spam / Pendientes / Papelera / Todos, con dry‑run y filtro por tipos de post; estrategia Papelera o Borrado definitivo.
- WP‑CLI completo y REST API propia (automatización lista para CI/CD).
- Multisite: ajustes de red con modo "enforce" para aplicar a todos los sitios.
- UI moderna: quick actions, contadores clicables, segment control de alcance, confirmación y accesibilidad.

## Multisite

- Los ajustes de red se guardan en `site_option:no_comments_network_settings`.
- "Enforce" fuerza los valores (enabled, REST, XML‑RPC y Woo Reviews) a todos los sitios.
- En la pantalla del sitio se muestra aviso cuando la red controla los ajustes.

## Instalación

1. Subí el ZIP desde Plugins → Añadir nuevo → Subir plugin (usá el release `.zip`).
2. Activá el plugin.
3. Ir a Ajustes → NO Comments.

Para Multisite, gestioná desde Network Admin → Ajustes → NO Comments (Network).

## Uso rápido

- Pestaña "Disable Comments": activá el toggle y (opcional) los cortes de REST/XML‑RPC.
- Pestaña "Delete Comments":
  - Elegí el alcance (Spam / Pendientes / Papelera / Todos) con el segment control o clickeando los contadores.
  - Activá "Simulación (dry‑run)" para ver cuántos comentarios afectás.
  - Si querés limitar por tipos de post, seleccioná en el checklist.
  - Elegí la estrategia: Borrar definitivamente o Mover a Papelera.
  - Para ejecución real, desmarcá dry‑run y escribí `DELETE` para confirmar.

## WP‑CLI

```bash
# Estado
wp no-comments status

# Activar / desactivar
wp no-comments enable
wp no-comments disable

# Borrar (dry-run primero)
wp no-comments delete --scope=spam --dry-run
wp no-comments delete --scope=all --types=post,page

# Woo reviews (compat)
wp no-comments woo-reviews on|off|status
```

## REST API

- `GET  /wp-json/no-comments/v1/settings` — snapshot efectivo (site/network).
- `POST /wp-json/no-comments/v1/settings` — body JSON: `level (site|network)`, `enabled`, `rest`, `xmlrpc`, `woo`, `enforce`.
- `POST /wp-json/no-comments/v1/actions/delete` — body JSON: `scope`, `types`, `strategy`, `dry_run`.

> Protegé con nonces/cookies de admin o credenciales de aplicación.

## Desarrollo

- Linter: WPCS + PSR‑12 (`phpcs.xml`).
- CI: GitHub Actions (`.github/workflows/ci.yml`).
- Composer scripts:

```bash
composer install
composer lint
composer run lint:report
composer fix
```

Estructura principal:

```text
no-comments/
  no-comments.php
  includes/
    Application/
      DeleteService.php
    Infrastructure/
      OptionsRepository.php
  languages/
  uninstall.php
```

## Changelog (resumen)

- 1.10.1 — UI polish: segmented control, contadores clicables, quick actions, confirm modal, aria‑live, tooltips; fix selector JS.
- 1.10.0 — REST API para settings/delete; release multisite + CLI ampliado.
- 1.9.0  — Ajustes de red con "enforce" y compatibilidad Woo en multisite.

> El changelog completo vive en `no-comments/readme.txt` (formato WordPress).

## Licencia

GPL‑2.0‑or‑later. Ver encabezado del plugin y `no-comments/readme.txt`.

## Enlaces

- [Web](https://mktmarketingdigital.com)
- [X/Twitter](https://x.com/akelaonline)
- [Instagram](https://www.instagram.com/akelaonline)
