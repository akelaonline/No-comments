# Entorno de desarrollo para "NO Comments" (WordPress)

Este entorno usa Docker Compose con WordPress, MariaDB, phpMyAdmin y WP-CLI. El plugin `no-comments/` se monta automáticamente dentro de `wp-content/plugins/no-comments`.

## Requisitos

- Docker Desktop (Compose v2)

## Puertos

- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8081

Puedes ajustar los puertos en `dev/.env`.

## Uso rápido

1. Configura variables en `dev/.env` si lo deseas (usuario/clave admin, puertos, etc.).
2. Levanta los servicios:

```bash
docker compose up -d
```

3. Espera a que el servicio `wpcli` termine la configuración. Revisa los logs:

```bash
docker compose logs wpcli
```

4. Accede a WordPress: http://localhost:8080
   - Usuario y clave del admin según `.env` (por defecto: `admin` / `admin123`).

5. Verifica que el plugin "NO Comments" esté activo y que el toggle funcione en `Ajustes → NO Comments`.

## Comandos útiles

- Estado de servicios:

```bash
docker compose ps
```

- Logs:

```bash
docker compose logs -f wordpress
```

- Ejecutar WP-CLI manualmente:

```bash
docker compose run --rm wpcli wp plugin list
```

- Apagar los servicios:

```bash
docker compose down
```

## Notas

- La base de datos y los archivos de WordPress se guardan en volúmenes Docker (`db_data`, `wp_data`).
- `wp-setup.sh` instalará WordPress, activará el plugin `no-comments` y dejará activada la opción global para deshabilitar comentarios.
- Cambia credenciales y correos del admin en `dev/.env` antes de exponer el entorno.
