=== NO Comments ===
Contributors: cascade
Tags: comments, disable comments, discussion
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Un toggle simple para habilitar o deshabilitar los comentarios (y pings) en todo el sitio.

== Descripción ==

Este plugin añade una página de ajustes llamada "NO Comments" bajo Ajustes y la coloca antes de la página de "Comentarios" (Discusión). 
Incluye un único interruptor (checkbox) que, al activarse, cierra los comentarios y pings en todo el sitio, quita el soporte de comentarios en los tipos de contenido públicos, oculta el menú de Comentarios del admin y el ícono de comentarios del admin bar, y bloquea el acceso directo a la pantalla de comentarios.

== Instalación ==

1. Copia la carpeta `no-comments/` en `wp-content/plugins/` de tu instalación de WordPress.
2. Activa el plugin desde el menú Plugins en el escritorio de WordPress.
3. Ve a Ajustes → NO Comments y usa el interruptor para activar o desactivar los comentarios.

== Cómo funciona ==

- Cuando el toggle está activado:
  - Fuerza `comments_open` y `pings_open` a `false`.
  - Retorna un array vacío para `comments_array`.
  - Quita el soporte de `comments` y `trackbacks` de todos los post types públicos.
  - Oculta el menú de Comentarios y el ícono del admin bar.
  - Bloquea el acceso directo a `edit-comments.php` en el admin.
- Cuando el toggle está desactivado, el sitio vuelve al comportamiento normal de WordPress.

== FAQ ==

= ¿Desaparece el menú de Comentarios? =

Sí. Cuando el toggle está activado, el menú de Comentarios desaparece del admin, también el ícono del admin bar, y se bloquea el acceso directo a la pantalla de Comentarios.

== Changelog ==

== 1.1.1 ==
* Oculta también el submenú Ajustes → Comentarios (Discusión).
* Bloquea el acceso directo a `options-discussion.php`.

== 1.1.0 ==
* Oculta el menú de Comentarios y el ícono del admin bar.
* Bloquea el acceso directo a la pantalla de Comentarios.
* Mejora de orden del submenú de Ajustes (antes de Discusión).

= 1.0.0 =
* Versión inicial
