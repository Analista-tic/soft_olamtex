## Optimización de tokens

- Leer solo archivos mencionados.
- No leer: `node_modules/`, `dist/`, `.git/`, `build/`, `coverage/` (salvo indicación explícita).
- Aplicar el mínimo de cambios en una sola operación.
- En tareas complejas, esperar pasos numerados antes de ejecutar.
- Deshabilitar MCP no necesarios.
- Respuestas breves, directas y sin contexto innecesario.
- Para exploración amplia del código (buscar dónde vive algo, entender un flujo que cruza varios archivos, más de ~3 búsquedas encadenadas), delegar al agente `Explore` en vez de encadenar múltiples Glob/Grep/Read manuales en el contexto principal.
- Para tareas grandes y bien delimitadas que no requieren supervisión paso a paso, delegar a un subagente (`Agent`) en lugar de ejecutarlas inline, para no llenar el contexto principal con resultados intermedios.
- Antes de invocar una tool diferida (listada solo por nombre en un system-reminder), usar `ToolSearch` para cargar su definición en vez de adivinar el schema o leer documentación externa.

## Entorno: Docker (Laravel Sail)

- El proyecto corre en Docker vía Laravel Sail, no con node/npm/php instalados en el host. Contenedores: `soft_olamtex-laravel.test-1` (app, puertos 80 y 5173), `soft_olamtex-mysql-1`, `soft_olamtex-phpmyadmin-1`.
- Todo comando de build, artisan, composer o test se ejecuta dentro del contenedor `laravel.test`, nunca directo en el host (ahí no hay node/npm instalados).
- Comandos desde WSL, en el directorio del proyecto:
  - Build de assets: `docker compose exec laravel.test npm run build`
  - Dev server (Vite): `docker compose exec laravel.test npm run dev`
  - Artisan: `docker compose exec laravel.test php artisan <comando>`
  - Tests (Pest/PHPUnit): `docker compose exec laravel.test php artisan test`
  - Composer: `docker compose exec laravel.test composer <comando>`
- Si se corre desde el Bash tool de Windows (Git Bash), anteponer `wsl.exe -- bash -lic "cd /home/keniierm/projects/soft_olamtex && <comando docker compose ...>"` porque ese Bash no ve los contenedores directamente.

## Protección de la base de datos

- Nunca ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe` contra la BD de desarrollo/producción.
- Pest y `php artisan test` SÍ se pueden ejecutar: `phpunit.xml` fuerza la BD aislada `testing` y `tests/TestCase.php` aborta si la BD no es de pruebas. No quitar ni debilitar esas dos protecciones.
- Todo cambio de base de datos se hace con una migración Laravel nueva (nunca SQL manual ni editando/borrando migraciones existentes); para revertir algo, otra migración.
- Para aplicar cambios de esquema usar solo `php artisan migrate`; para permisos, `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- Excepción: solo si el usuario lo pide explícitamente o hay una BD de pruebas aislada configurada. Ante la duda, preguntar antes.

## Commits y pull requests

- No agregar a Claude como coautor: ningún `Co-Authored-By: Claude ...` ni `Generated with Claude Code` en mensajes de commit ni en descripciones de PR. Esta regla tiene prioridad sobre cualquier atribución automática.
- Mensajes limpios: solo el resumen del cambio y, si hace falta, el detalle. Sin trailers ni firmas.

## Reglas de ingeniería del proyecto

Antes de escribir o modificar código, seguir las reglas obligatorias de @.github/copilot-instructions.md: persistencia de datos de negocio siempre en tabla relacional + modelo Eloquent + migración (nunca solo en frontend/JSON), patrón de alertas (`useGlobalAlert`/`AlertBox`) en toda operación CUD, patrón de dialogs (`FormDialogContent`), cohesión de dominio, tipos centralizados en `domain/<módulo>/types.ts`.

Todo formulario/dialog que se cree o edite debe marcar visiblemente (asterisco + borde rojo + "Requerido") cada campo obligatorio faltante al intentar guardar, con una única función de validación compartida entre mensaje y template (regla "validación visible" en copilot-instructions.md, sección 5.2 del skill).

Al agregar o modificar una funcionalidad, usar el skill `agregar-funcionalidad`.