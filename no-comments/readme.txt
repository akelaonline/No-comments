=== NO Comments ===
Contributors: akelaonline
Donate link: https://mktmarketingdigital.com/
Tags: comments, disable comments, discussion, spam, delete comments
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.10.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin minimalista para cerrar TODOS los comentarios del sitio y borrar comentarios no deseados (spam/pendientes/papelera/todos). Incluye comandos WP‑CLI.

== Descripción ==

- Toggle global para deshabilitar comentarios y pings en todo el sitio.
- Oculta menú de Comentarios y el ícono del admin bar; bloquea accesos directos.
- Opcionalmente corta endpoints REST de comentarios y el método XML‑RPC `wp.newComment`.
- Comandos WP‑CLI para automatizar (status/enable/disable/delete).
- Compatibilidad WooCommerce: opción para mantener reseñas de productos aunque el cierre global esté activo.
- Multisite: ajustes de red con modo "enforce" para aplicar a todos los sitios (incluye REST/XML‑RPC/Woo reviews).

== Enlaces ==

- Sitio: https://mktmarketingdigital.com
- X/Twitter: https://x.com/akelaonline
- Instagram: https://www.instagram.com/akelaonline

== Instalación ==

1. Copia la carpeta `no-comments/` dentro de `wp-content/plugins/`.
2. Activa el plugin en el administrador de WordPress.
3. Ve a Ajustes → NO Comments.

== Uso ==

= Cerrar comentarios globalmente =
- Ajustes → NO Comments → pestaña "Disable Comments" → activar checkbox.

= Borrar comentarios =
- Ajustes → NO Comments → pestaña "Delete Comments".
- Elige alcance: solo Spam, Pendientes, Papelera o TODOS.
- Escribe `DELETE` para confirmar y ejecuta.

= Compatibilidad (WooCommerce) =
- Ajustes → NO Comments → sección "Compatibilidad" → activa "Mantener reseñas de productos" para conservar reviews en productos.

== WP‑CLI ==

```
wp no-comments status
wp no-comments enable
wp no-comments disable
wp no-comments delete --scope=spam      # spam|pending|trash|all
wp no-comments delete --scope=spam --dry-run
wp no-comments delete --scope=all --types=post,page
wp no-comments woo-reviews on|off|status
```

== FAQ ==

= ¿Por qué falla al subir el ZIP? =
Si estás montando la carpeta `no-comments/` por bind mount (por ejemplo con Docker), WordPress no puede sobreescribirla. Activa el plugin directamente desde la lista de plugins.

== Changelog ==

= 1.10.1 =
* UI: segmented control para elegir alcance (Spam/Pendientes/Papelera/Todos).
* UI: contadores clicables y quick actions sin recarga.
* UI: modal de confirmación adicional y aria-live para feedback.
* UI: tooltips en opciones de API y estrategia; notice de resultado con detalles.
* Fix: selector de submit en JS inline (compatibilidad con WP admin).

= 1.10.0 =
* REST API: endpoints `no-comments/v1/settings` (GET/POST) y `no-comments/v1/actions/delete` (POST) con soporte para `dry_run`, `types` y nivel `site|network`.

= 1.9.0 =
* Multisite: página de ajustes de red con opción de "enforce" (forzar valores en todos los sitios). Incluye: estado global, REST, XML‑RPC, y compatibilidad WooCommerce.
* Aviso en ajustes del sitio cuando los valores están controlados por la red.
* CSS condicional respetando la compatibilidad con WooCommerce también cuando está forzada por red.

= 1.8.0 =
* Delete Comments: estrategia de borrado elegible: borrar permanentemente o mover a Papelera (reversible). "Vaciar Papelera" siempre elimina definitivamente.
* Delete Comments: soporte combinado con filtro por tipos de post.

= 1.7.0 =
* Delete Comments: filtro opcional por tipos de post (UI y lógica).
* WP‑CLI: `delete` admite `--types=post,page` además de `--dry-run`.

= 1.6.0 =
* Admin bar: toggle rápido ON/OFF con nonce y permisos.
* Enlace directo a Ajustes en la lista de plugins.
* Refinos de UI y textos en la página de opciones.

= 1.5.0 =
* Compatibilidad WooCommerce: opción para mantener reseñas de productos aunque el cierre global esté activo (UI y WP‑CLI).
* Menú de Comentarios ya no se oculta si se mantienen reseñas.
* Pequeños ajustes de UI y CSS condicional.

= 1.4.0 =
* UI mejorada: estilos sutiles en admin, help tab y contador de estados.
* Site Health: test que reporta el estado del bloqueo global.
* "Delete Comments": añade modo simulación (dry‑run) con conteo previo.
* Ajustes avanzados: toggles para REST y XML‑RPC visibles en la UI.

= 1.3.0 =
* Ajustes: toggles para deshabilitar REST `wp/v2/comments` y método XML‑RPC `wp.newComment` (configurables).
* Estado visible en la pestaña de ajustes.

= 1.2.0 =
* Añade pestaña "Delete Comments" con borrado masivo por alcance (spam/pendientes/papelera/todos) y confirmación.
* WP‑CLI: `status`, `enable`, `disable`, `delete`.
* Hardening: remueve endpoints REST de comentarios y método XML‑RPC `wp.newComment` cuando el toggle está activo.

= 1.1.1 =
* Oculta submenú Ajustes → Comentarios (Discusión) y bloquea acceso directo.

= 1.1.0 =
* Oculta el menú de Comentarios y el ícono del admin bar. Bloquea la pantalla de Comentarios. Mejora de orden del submenú.

= 1.0.0 =
* Versión inicial.
