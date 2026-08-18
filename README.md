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

## Akela WordPress

> **Production-grade WordPress infrastructure for performance, SEO, automation and AI agents.**

NO Comments forma parte de la familia **Akela WordPress**:

- **[WP-Nerve](https://github.com/akelaonline/WP-Nerve)** — native control layer / MCP gateway para agentes y WordPress.
- **Akela SEO** — SEO técnico y automatizable para WordPress.
- **PageRelay** — AI-to-WordPress deployment layer para páginas nativas, editables y reversibles.
- **[NO Comments](https://github.com/akelaonline/No-comments)** — cierre y limpieza integral de comentarios, con REST y WP-CLI.
- **Tucho Performance** — performance, caché y optimización WordPress 100% local.

Los productos son independientes, pero comparten los mismos principios: **self-hosted cuando importa, APIs explícitas, seguridad por diseño, observabilidad y operación real en producción.**

### Professional ecosystem

- **[MKT Marketing Digital](https://mktmarketingdigital.com)** — agencia de marketing digital, implementación y growth.
- **[The Thing](https://thethingapp.com)** — producto de MKT para atención y ventas con IA.
- **[Marketing Digital Experience](https://marketingdigitalexperience.com)** — consultoría, formación y transferencia de conocimiento en IA aplicada.
- **[Nubelytics](https://nubelytics.com)** — analytics + AI para ecommerce.
- **[Zantal](https://zantal.ai)** — agentic commerce intelligence.

---

## Autor, soporte y contacto

Built by **Alejandro Daniel José · Akela**.

[![GitHub](https://img.shields.io/badge/GitHub-akelaonline-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/akelaonline)
[![Instagram](https://img.shields.io/badge/Instagram-%40akelaonline-E4405F?style=for-the-badge&logo=instagram&logoColor=white)](https://www.instagram.com/akelaonline/)
[![MKT](https://img.shields.io/badge/MKT-Marketing_Digital-4285F4?style=for-the-badge)](https://mktmarketingdigital.com)
[![MDE](https://img.shields.io/badge/MDE-AI_Consulting-111111?style=for-the-badge&logo=openai&logoColor=white)](https://marketingdigitalexperience.com)
[![Email](https://img.shields.io/badge/Email-alejandro%40mktmarketingdigital.com-0A66C2?style=for-the-badge&logo=gmail&logoColor=white)](mailto:alejandro@mktmarketingdigital.com)

- Para bugs y mejoras técnicas: [GitHub Issues](https://github.com/akelaonline/No-comments/issues).
- Para vulnerabilidades: [SECURITY.md](SECURITY.md).
- Para implementación, integraciones o trabajo profesional: [MKT Marketing Digital](https://mktmarketingdigital.com).
- Para consultoría y capacitación en IA: [Marketing Digital Experience](https://marketingdigitalexperience.com).

---

## Licencia

**GPL-2.0-or-later** — software libre. Ver [LICENSE](LICENSE).

---

## Desarrollo

¿Querés contribuir? Mirá [CONTRIBUTING.md](CONTRIBUTING.md). El CI valida automáticamente PHPCS (PHP 7.4–8.5) y el **WordPress Plugin Check** oficial en cada push.
