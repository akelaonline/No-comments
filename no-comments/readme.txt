=== NO Comments ===
Contributors: akelaonline
Donate link: https://akela.dev/seo
Tags: comments, disable comments, discussion, spam, delete comments
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cierra comentarios y pings globalmente y limpia comentarios de forma segura, con excepciones por tipo de contenido, cierre automático, limpieza automática, WooCommerce, Multisite, REST y WP-CLI.

== Descripción ==

NO Comments es un plugin liviano para sitios que no necesitan conversación pública y quieren cerrar correctamente la superficie de comentarios de WordPress.

Características principales:

* Cierre global de comentarios y pings.
* Excepciones por tipo de contenido: tipos seleccionados conservan comentarios (WooCommerce "product" incluido como excepción cuando corresponde).
* Cierre automático por antigüedad: cierra formularios y pings en contenido con más de N días, sin apagar el bloqueo del sitio.
* Limpieza automática de spam por WP-Cron (diaria, dos veces al día o semanal), con registro de cada ejecución.
* Oculta la UI de comentarios cuando corresponde y bloquea accesos directos.
* Puede retirar los endpoints REST de comentarios y `wp.newComment` de XML-RPC.
* Mantiene opcionalmente las reseñas de productos de WooCommerce sin reabrir productos que tengan reviews cerradas individualmente.
* Borrado masivo por Spam / Pendientes / Papelera / Todos.
* Dry-run antes de cualquier limpieza real.
* Filtro por tipos de contenido.
* Estrategia de borrado definitivo o movimiento reversible a Papelera.
* WP-CLI y REST API para automatización administrativa.
* Multisite con configuración de red y modo `enforce`.
* Import/export de ajustes en JSON (UI, REST y WP-CLI).
* Performance: con el bloqueo activo las consultas de comentarios se cortan sin tocar la base de datos, y los feeds de comentarios se desactivan.
* Purga de caché de los posts afectados tras un borrado masivo (acción `no_comments_after_delete` e integración con Tucho).
* Site Health para verificar rápidamente el estado del bloqueo.

No envía datos a servicios externos y no requiere una cuenta de terceros.

== Instalación ==

1. Copia la carpeta `no-comments/` dentro de `wp-content/plugins/`, o instala el ZIP generado desde un release.
2. Activa el plugin.
3. Ve a Ajustes → NO Comments.
4. En Multisite, la configuración de red está disponible en Network Admin → Ajustes → NO Comments.

== Uso ==

= Cerrar comentarios globalmente =

Ajustes → NO Comments → pestaña "Disable Comments" → activa el bloqueo global.

Cuando el bloqueo está activo, NO Comments cierra formularios y pings y puede bloquear las entradas REST/XML-RPC asociadas a comentarios.

= Excepciones por tipo de contenido =

En la pestaña "Disable Comments", el campo "Excepciones" permite conservar comentarios y pings en los tipos de contenido seleccionados aunque el bloqueo global esté activo. El menú de Comentarios y las consultas siguen funcionando para esos tipos. WooCommerce se mantiene como excepción automáticamente cuando la compatibilidad de reseñas está activa.

= Mantener reseñas WooCommerce =

Activa "Mantener reseñas de productos (WooCommerce)" para conservar reviews de productos aunque el resto del sitio tenga los comentarios cerrados. El plugin respeta el estado abierto/cerrado de cada producto.

= Cierre automático por antigüedad =

En "Cierre automático" indica un número de días: los formularios y pings de contenido más antiguo quedan cerrados sin necesidad de apagar el resto del sitio. Aplica cuando el bloqueo global está apagado. Usa 0 para desactivarlo.

= Limpieza automática de spam =

En "Limpieza automática" activa el borrado periódico de spam vía WP-Cron y elige la frecuencia (Diaria, Dos veces al día o Semanal). Cada ejecución borra definitivamente el spam y queda registrada (fecha y cantidad). También puedes ejecutarla manualmente desde WP-CLI.

= Limpiar comentarios =

Ajustes → NO Comments → pestaña "Delete Comments".

1. Elige Spam, Pendientes, Papelera o Todos.
2. Opcionalmente limita por tipo de contenido.
3. Elige Borrar permanentemente o Mover a Papelera.
4. Ejecuta primero con "Simulación (dry-run)".
5. Para una ejecución real, desmarca el dry-run y escribe `DELETE`.

"Todos + Mover a Papelera" conserva los comentarios en Papelera y no la vacía dentro de la misma operación. "Vaciar Papelera" siempre es definitivo.

= Importar / Exportar ajustes =

En Ajustes → NO Comments → "Importar / Exportar ajustes" puedes descargar un JSON con la configuración actual (útil como respaldo o para clonarla en otro sitio) e importarla desde un archivo exportado. Disponible también vía REST y WP-CLI.

= Performance y feeds =

Con el bloqueo global activo, NO Comments corta las consultas de comentarios en el frontend (no toca la base de datos) y desactiva los feeds de comentarios: se elimina el link de descubrimiento y el acceso directo redirige al home.

== WP-CLI ==

```
wp no-comments status
wp no-comments enable
wp no-comments disable
wp no-comments delete --scope=spam --dry-run
wp no-comments delete --scope=all --types=post,page --strategy=delete
wp no-comments woo-reviews on|off|status
wp no-comments settings export --file=no-comments.json
wp no-comments settings import no-comments.json
wp no-comments exceptions list
wp no-comments exceptions add page
wp no-comments exceptions remove page
wp no-comments auto-close 30
wp no-comments auto-close status
wp no-comments cleanup status
wp no-comments cleanup enable --interval=weekly
wp no-comments cleanup run
wp no-comments cleanup disable
```

== REST API ==

Endpoints administrativos:

* `GET /wp-json/no-comments/v1/settings`
* `POST /wp-json/no-comments/v1/settings`
* `POST /wp-json/no-comments/v1/actions/delete`
* `GET /wp-json/no-comments/v1/settings/export`
* `POST /wp-json/no-comments/v1/settings/import` (body: `level`, `settings`)

Los endpoints usan comprobaciones de capabilities de WordPress. Autentica con cookies/nonces de administración o Application Passwords sobre HTTPS.

== Multisite ==

La pantalla de red permite definir estado global, REST, XML-RPC y compatibilidad WooCommerce. Cuando `enforce` está activo, los sitios individuales no pueden sobrescribir los ajustes efectivos.

== Privacidad ==

NO Comments no recopila telemetría, no crea cuentas externas y no envía contenido fuera de tu instalación de WordPress.

== Preguntas frecuentes ==

= ¿Borra comentarios al activar el bloqueo? =

No. Deshabilitar comentarios y borrar comentarios son operaciones separadas.

= ¿Puedo comprobar qué se borraría antes de hacerlo? =

Sí. El dry-run está activado por defecto en la herramienta de limpieza.

= ¿Puedo conservar las reseñas de WooCommerce? =

Sí. La opción de compatibilidad mantiene reviews de productos y respeta si un producto concreto las tiene abiertas o cerradas.

= ¿Funciona en Multisite? =

Sí. Incluye ajustes de red y un modo `enforce` para aplicar una configuración común a todos los sitios.

== Changelog ==

= 1.13.0 =
* Nuevo: excepciones por tipo de contenido — los tipos seleccionados conservan comentarios y pings con el bloqueo global activo (menú, consultas y formularios). "product" se incluye automáticamente cuando la compatibilidad WooCommerce está activa.
* Nuevo: cierre automático por antigüedad — cierra formularios y pings en contenido con más de N días, sin apagar el bloqueo del sitio.
* Nuevo: limpieza automática de spam por WP-Cron — frecuencia diaria, dos veces al día o semanal, con registro de cada ejecución y lock anti-concurrencia.
* Nuevo: purga de caché tras borrado masivo — acción `no_comments_after_delete` con los posts afectados e integración con Tucho (`tucho_purge_post`).
* WP-CLI: comandos `exceptions list|add|remove`, `auto-close` y `cleanup status|run|enable|disable`.
* REST API: `/settings` acepta `exceptions`, `auto_close_days`, `auto_cleanup` y `auto_cleanup_interval`; export/import los incluye.
* UI: campos "Excepciones", "Cierre automático" y "Limpieza automática" en la pestaña Disable Comments.

= 1.12.0 =
* Performance: las consultas de comentarios se cortan en el frontend cuando el bloqueo global está activo (hook `comments_pre_query`), sin consultas innecesarias a la base de datos.
* Feeds: se desactivan los feeds de comentarios (link de descubrimiento eliminado y acceso directo redirigido al home).
* Import/export: nuevos endpoints REST `GET /settings/export` y `POST /settings/import`.
* Import/export: tarjeta "Importar / Exportar ajustes" en la pantalla de ajustes (descarga JSON y subida de archivo con validación de tamaño y formato).
* WP-CLI: nuevos comandos `wp no-comments settings export` y `wp no-comments settings import`.
* WP-CLI: `status` ahora muestra el estado efectivo (considera el modo `enforce` de red) y `delete` admite `--strategy=trash`.
* Branding: autoría y enlaces alineados con Akela SEO y Tucho (Akela @akelaonline, akela.dev).

= 1.11.0 =
* Fix crítico: el bloqueo de nuevas inserciones usa `pre_comment_approved`, hook compatible con `WP_Error`, evitando respuestas inválidas desde `preprocess_comment`.
* Fix crítico: "Todos + Mover a Papelera" ya no vacía la Papelera dentro de la misma operación.
* Hardening: los lotes de borrado se detienen de forma segura si otra extensión impide modificar comentarios, evitando loops infinitos.
* WooCommerce: mantener reviews ya no fuerza a abrir productos con reviews cerradas individualmente.
* WooCommerce: el ajuste puede preconfigurarse aunque WooCommerce esté temporalmente inactivo.
* Multisite: cuando la red fuerza configuración, la pantalla de sitio deja de ofrecer un formulario local engañoso y el toggle rápido no sobrescribe opciones locales.
* Multisite: el REST de ajustes rechaza cambios de nivel sitio cuando la red está en modo `enforce`.
* Uninstall: limpia opciones de todos los sitios de una red, no sólo los primeros 100.
* Metadata: añade requisitos de WordPress/PHP, Update URI y autoría pública.
* Tooling: CI ampliada a PHP 7.4–8.5 y WordPress Plugin Check.

= 1.10.1 =
* UI: segmented control para elegir alcance (Spam/Pendientes/Papelera/Todos).
* UI: contadores clicables y quick actions sin recarga.
* UI: modal de confirmación adicional y aria-live para feedback.
* UI: tooltips en opciones de API y estrategia; notice de resultado con detalles.
* Fix: selector de submit en JS inline (compatibilidad con WP admin).

= 1.10.0 =
* REST API: endpoints `no-comments/v1/settings` (GET/POST) y `no-comments/v1/actions/delete` (POST) con soporte para `dry_run`, `types` y nivel `site|network`.

= 1.9.0 =
* Multisite: página de ajustes de red con opción de `enforce`. Incluye estado global, REST, XML-RPC y compatibilidad WooCommerce.
* Aviso en ajustes del sitio cuando los valores están controlados por la red.

= 1.8.0 =
* Delete Comments: estrategia elegible entre borrado permanente y Papelera.
* Delete Comments: soporte combinado con filtro por tipos de post.

= 1.7.0 =
* Delete Comments: filtro opcional por tipos de post.
* WP-CLI: `delete` admite `--types=post,page` y `--dry-run`.

= 1.6.0 =
* Admin bar: toggle rápido ON/OFF con nonce y permisos.
* Enlace directo a Ajustes en la lista de plugins.

= 1.5.0 =
* Compatibilidad WooCommerce para mantener reseñas de productos.

= 1.4.0 =
* UI mejorada, integración con Site Health y modo dry-run.

= 1.3.0 =
* Toggles para REST de comentarios y XML-RPC `wp.newComment`.

= 1.2.0 =
* Herramienta de borrado masivo y comandos WP-CLI.

= 1.1.1 =
* Oculta Discusión y bloquea acceso directo.

= 1.1.0 =
* Oculta menú de Comentarios y el icono del admin bar.

= 1.0.0 =
* Versión inicial.
