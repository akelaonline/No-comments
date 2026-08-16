# NO Comments

### Apagá los comentarios de WordPress de una vez — sin romper WooCommerce, sin penalizar el rendimiento, sin spam acumulado.

[![Quality](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml/badge.svg)](https://github.com/akelaonline/No-comments/actions/workflows/ci.yml)
[![Version](https://img.shields.io/badge/version-1.14.0-111827)](https://github.com/akelaonline/No-comments/releases)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.5-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-16a34a)
[![Descargar ZIP](https://img.shields.io/badge/descargar-zip-0ea5e9)](https://github.com/akelaonline/No-comments/releases/latest)

> **English:** NO Comments is a focused WordPress plugin that closes comments and pings site-wide in one click — with optional REST/XML-RPC hardening, safe bulk cleanup (dry-runs, Trash, post-type filters), automatic spam deletion, WooCommerce review support and full Multisite control. 100% local, telemetry-free.

---

## ¿Por qué NO Comments?

Desactivar “Permitir comentarios” en WordPress **no es suficiente**. Los formularios siguen apareciendo en temas y plugins, las APIs siguen expuestas, los feeds de comentarios siguen vivos, los menús del admin siguen molestando y el spam sigue acumulándose.

NO Comments resuelve el problema completo en **un solo lugar**:

- ✅ **Cierra comentarios y pings en todo el sitio** — frontend, admin, APIs y feeds.
- ✅ **Rendimiento real**: con el bloqueo activo, **cero consultas a la base de datos** por comentarios en el frontend.
- ✅ **Limpieza segura**: borrá spam, pendientes o todo con **dry-run** (simulación previa), filtros por tipo de contenido y opción de Papelera reversible.
- ✅ **WooCommerce intacto**: mantené las reseñas de productos aunque el resto del sitio esté cerrado.
- ✅ **Automático**: limpieza de spam por cron y cierre de contenido antiguo por antigüedad.
- ✅ **100% local y sin telemetría**: no envía datos a ningún servicio externo.

---

## Antes / Después

| | WordPress por defecto | Con NO Comments |
|---|---|---|
| Formularios y pings | Dependen de cada tema/plugin | **Cerrados en todo el sitio** |
| Endpoints REST `wp/v2/comments` | Expuestos | **Bloqueables** |
| XML-RPC `wp.newComment` | Activo | **Bloqueable** |
| Feeds de comentarios | Activos | **Desactivados** |
| Consultas de comentarios (frontend) | Cada página consulta la BD | **0 con bloqueo activo** |
| Spam acumulado | Crecimiento sin control | **Borrado a demanda o automático** |
| Reseñas WooCommerce | Se apagan con el cierre | **Se mantienen** |

---

## Lo que hace por tu sitio

### Cierre total en un clic
- Cierra formularios y pings en todos los post types públicos.
- Oculta el menú de Comentarios, el submenú Discusión y el ícono de la barra de administración.
- Bloquea el acceso directo a las pantallas de comentarios/discusión.
- Desactiva los feeds de comentarios (descubrimiento y acceso directo).

### Rendimiento (importa para SEO)
- **Short-circuit de consultas**: cuando el bloqueo está activo, las consultas de comentarios se cortan antes de tocar la base de datos. Menos carga, páginas más rápidas, mejor Core Web Vitals.
- Cero scripts o estilos en el frontend: el plugin solo trabaja donde hace falta.

### Seguridad
- Opcional: elimina los endpoints REST de comentarios y el método XML-RPC `wp.newComment`.
- Rechaza nuevas inserciones con `WP_Error` estándar de WordPress (no respuestas inválidas).

### WooCommerce
- **Mantener reseñas de productos** activa la excepción automática `product`.
- Respeta el estado abierto/cerrado de cada producto: no fuerza reviews en productos que las tienen cerradas.

### Excepciones por tipo de contenido
- Conservá comentarios (y pings) en los tipos que elijas — páginas, productos, o tu CPT personalizado — mientras el resto del sitio está cerrado.

### Cierre automático por antigüedad
- Configurá N días: los posts viejos cierran comentarios solos, sin apagar el bloqueo del sitio. Ideal para sitios editoriales que quieren conversación solo en contenido reciente.

### Limpieza masiva segura
- Alcances: **Spam / Pendientes / Papelera / Todos**, con contadores clicables.
- **Dry-run activado por defecto**: primero ves cuántos se borrarían.
- Estrategias: borrado definitivo o **mover a Papelera** (reversible).
- Filtro por tipos de contenido.
- Confirmación explícita (`DELETE`) antes de ejecutar.
- **Limpieza automática por WP-Cron**: diaria, dos veces al día o semanal, con registro de cada ejecución.

### Multisite
- Ajustes de red con modo **enforce**: definí la política una vez y aplicala a todos los sitios. Los sitios no pueden sobrescribirla.

### Automatización para agencias
- **WP-CLI**: `enable`, `disable`, `delete --scope --types --strategy`, `exceptions`, `auto-close`, `cleanup`, `settings export|import`.
- **REST API propia**: `no-comments/v1/settings`, `/actions/delete`, `/settings/export`, `/settings/import`.
- **Import/Export de ajustes en JSON**: cloná la configuración entre clientes en segundos.

---

## Instalación (30 segundos)

1. Descargá el **ZIP** desde [Releases](https://github.com/akelaonline/No-comments/releases/latest).
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin**.
3. Activá y andá a **Ajustes → NO Comments**.

> Multisite: gestioná la política global desde **Network Admin → Ajustes → NO Comments**.

---

## Uso rápido

### Desde el administrador
- **Ajustes → NO Comments → Disable Comments**: activá el bloqueo y configurá APIs, excepciones, cierre por antigüedad y limpieza automática.
- **Ajustes → NO Comments → Delete Comments**: simulá con dry-run o ejecutá la limpieza por alcance.

### Desde WP-CLI

```bash
# Estado y bloqueo
wp no-comments status
wp no-comments enable
wp no-comments disable

# Borrado (siempre dry-run primero)
wp no-comments delete --scope=spam --dry-run
wp no-comments delete --scope=all --types=post,page --strategy=delete

# Excepciones y cierre automático
wp no-comments exceptions add page
wp no-comments auto-close 30

# Limpieza automática de spam
wp no-comments cleanup status
wp no-comments cleanup enable --interval=weekly
wp no-comments cleanup run

# Respaldo / clonado de configuración
wp no-comments settings export --file=no-comments.json
wp no-comments settings import no-comments.json
```

### Desde la REST API

```text
GET  /wp-json/no-comments/v1/settings
POST /wp-json/no-comments/v1/settings
POST /wp-json/no-comments/v1/actions/delete
GET  /wp-json/no-comments/v1/settings/export
POST /wp-json/no-comments/v1/settings/import
```

Autenticá con Application Passwords sobre HTTPS o cookies/nonces de administración.

---

## Privacidad

NO Comments **no** recopila telemetría, **no** crea cuentas externas, **no** llama a servicios en la nube y **no** envía contenido fuera de tu instalación. Todo queda en tu WordPress.

---

## Ecosistema Akela

Plugin creado y mantenido por **Akela** ([@akelaonline](https://github.com/akelaonline)) — desarrollo WordPress orientado a SEO y rendimiento:

- **Akela SEO** — SEO técnico completo para sitios editoriales y e-commerce.
- **Tucho Performance** — caché y optimización de rendimiento, 100% local.

[![GitHub](https://img.shields.io/badge/GitHub-akelaonline-111827)](https://github.com/akelaonline)
[![Instagram](https://img.shields.io/badge/Instagram-%40akelaonline-E4405F)](https://www.instagram.com/akelaonline/)

## Soporte

- Reportá bugs y proponé mejoras en [GitHub Issues](https://github.com/akelaonline/No-comments/issues).
- Para vulnerabilidades de seguridad, seguí [SECURITY.md](SECURITY.md) (reporte privado).

## Licencia

**GPL-2.0-or-later** — software libre. Ver [LICENSE](LICENSE).

---

## Desarrollo

¿Querés contribuir? Mirá [CONTRIBUTING.md](CONTRIBUTING.md). El CI valida automáticamente PHPCS (PHP 7.4–8.5) y el **WordPress Plugin Check** oficial en cada push.
