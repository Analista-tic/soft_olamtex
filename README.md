# Soft Olamtex (ERP)

Laravel 12 + Vue 3 + Inertia + Vite, con MySQL 8.4.

## Formas de correr el proyecto

Hay dos opciones: **local** (sin Docker) o **Docker**. Elegir una.

## Opción 1: Local (sin Docker)

Requisitos: PHP >= 8.2, Composer, Node.js/npm y MySQL corriendo en tu máquina.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

En `.env` dejar la BD local (`DB_HOST=127.0.0.1`, `DB_DATABASE=soft_olamtex_db`, usuario/clave de tu MySQL). Luego:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
composer dev        # servidor + cola + Vite juntos
```

Variantes: `composer dev:fast` (sin cola) y `composer dev:ssr` (con SSR). Abrir http://127.0.0.1:8000.

> Si usas esta opción, en `vite.config.ts` el `server.host` debe volver a `127.0.0.1` y en `.env` `DB_HOST=127.0.0.1` (no `mysql`).

## Opción 2: Docker (Laravel Sail)

Requisitos: Docker Desktop corriendo. En Windows, usar **Git Bash** o PowerShell (Sail no soporta Git Bash, por eso se usa `docker compose` directo).

### Primera vez

1. **Instalar dependencias PHP** (sin PHP local, usando un contenedor temporal):
   ```bash
   export MSYS_NO_PATHCONV=1   # solo Git Bash: evita que convierta rutas /app
   docker run --rm -v "$(pwd -W):/app" -w /app composer:2 install --ignore-platform-reqs
   ```
   En PowerShell: `docker run --rm -v "${PWD}:/app" -w /app composer:2 install --ignore-platform-reqs`

2. **Crear el `.env`**:
   ```bash
   cp .env.example .env
   ```
   Ajustar estos valores:
   ```
   DB_HOST=mysql
   DB_USERNAME=sail
   DB_PASSWORD=password
   WWWUSER=1000
   WWWGROUP=1000
   ```
   `WWWUSER`/`WWWGROUP` son obligatorios en Windows; sin ellos el build falla en `groupadd`.

3. **Construir y levantar** (el primer build tarda; luego queda en caché):
   ```bash
   docker compose build
   docker compose up -d
   ```

4. **Inicializar la app** (`laravel.test` es el nombre del servicio de la app en `compose.yaml`):
   ```bash
   docker compose exec laravel.test php artisan key:generate   # solo si APP_KEY está vacío
   docker compose exec laravel.test php artisan migrate --seed
   docker compose exec laravel.test php artisan storage:link
   docker compose exec laravel.test npm install
   ```

5. **Arrancar Vite** (frontend):
   ```bash
   docker compose exec -d laravel.test npm run dev
   ```

6. Abrir http://localhost

### Día a día

```bash
docker compose up -d                              # encender
docker compose exec -d laravel.test npm run dev   # Vite en segundo plano
docker compose down                               # apagar (conserva la BD)
```

Solo se vuelve a construir la imagen si cambia `compose.yaml` o el Dockerfile. `docker compose down -v` borra también la base de datos.

Cola de trabajos (si se necesita):
```bash
docker compose exec -d laravel.test php artisan queue:listen --tries=1
```

Tests:
```bash
docker compose exec laravel.test php artisan test
```

### Alias opcionales (Git Bash)

```bash
alias sail='docker compose exec laravel.test'
sail php artisan migrate
sail npm run dev
```

### Configuración específica de Docker

- `compose.yaml`: el servicio `mysql` usa `--log-bin-trust-function-creators=1`. Es necesario porque hay migraciones que crean triggers (`product_sheet_operations`) y el usuario `sail` no tiene privilegio SUPER.
- `vite.config.ts`: `server.host` es `0.0.0.0` para que el navegador de Windows llegue a Vite dentro del contenedor. Para que **otros equipos de la red** vean el JS, `server.origin` y `server.hmr.host` deben usar tu IP LAN en lugar de `localhost`; ver [docs/ENTORNO_PRUEBAS_EN_RED.md](docs/ENTORNO_PRUEBAS_EN_RED.md).

### Problemas comunes

| Error | Causa / solución |
|---|---|
| `working directory ... 'C:/Program Files/Git/app' is invalid` | Git Bash convierte `/app`. Usar `export MSYS_NO_PATHCONV=1`. |
| `composer.json not found` en el contenedor | Volumen vacío. Usar `$(pwd -W)` en Git Bash o `${PWD}` en PowerShell. |
| `groupadd: invalid group ID 'sail'` | Faltan `WWWUSER`/`WWWGROUP` en `.env`. |
| `Unsupported operating system [MINGW64...]` | `vendor/bin/sail` no corre en Git Bash. Usar `docker compose exec laravel.test ...`. |
| `1419 You do not have the SUPER privilege` en migrate | Falta `--log-bin-trust-function-creators=1` en el servicio mysql. |
| Invocar docker desde la distro `docker-desktop` de WSL | No soportado. Usar PowerShell/Git Bash o habilitar la integración WSL de otra distro. |
