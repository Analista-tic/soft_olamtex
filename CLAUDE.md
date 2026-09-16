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

## Reglas de ingeniería del proyecto

Antes de escribir o modificar código, seguir las reglas obligatorias de @.github/copilot-instructions.md: persistencia de datos de negocio siempre en tabla relacional + modelo Eloquent + migración (nunca solo en frontend/JSON), patrón de alertas (`useGlobalAlert`/`AlertBox`) en toda operación CUD, patrón de dialogs (`FormDialogContent`), cohesión de dominio, tipos centralizados en `domain/<módulo>/types.ts`.

Al agregar o modificar una funcionalidad, usar el skill `agregar-funcionalidad`.