## Optimización de tokens

- Leer solo archivos mencionados.
- No leer: `node_modules/`, `dist/`, `.git/`, `build/`, `coverage/` (salvo indicación explícita).
- Aplicar el mínimo de cambios en una sola operación.
- En tareas complejas, esperar pasos numerados antes de ejecutar.
- Deshabilitar MCP no necesarios.
- Respuestas breves, directas y sin contexto innecesario.

## Reglas de ingeniería del proyecto

Antes de escribir o modificar código, seguir las reglas obligatorias de @.github/copilot-instructions.md: persistencia de datos de negocio siempre en tabla relacional + modelo Eloquent + migración (nunca solo en frontend/JSON), patrón de alertas (`useGlobalAlert`/`AlertBox`) en toda operación CUD, patrón de dialogs (`FormDialogContent`), cohesión de dominio, tipos centralizados en `domain/<módulo>/types.ts`.

Al agregar o modificar una funcionalidad, usar el skill `agregar-funcionalidad`.