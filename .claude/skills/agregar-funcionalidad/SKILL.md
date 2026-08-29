---
name: agregar-funcionalidad
description: Checklist obligatorio para agregar o modificar cualquier funcionalidad en el ERP Olamtex (backend Laravel + frontend Vue/Inertia). Úsalo antes de dar por terminada una tarea de desarrollo en cualquier módulo (Rollos, Inventario, Control de Lotes, Compras, o cualquier otro).
---

# Agregar funcionalidad — Olamtex

Sigue este checklist completo antes de marcar una funcionalidad como lista. Las reglas detalladas de convenciones (nombres, estructura de carpetas, patrones existentes) están en `.github/copilot-instructions.md` — este skill es el checklist accionable que las aplica paso a paso.

## 1. Backend

- Controlador en el dominio correcto bajo `app/Http/Controllers/<Dominio>/`.
- Rutas en español, agrupadas con `Route::prefix()`/`name()`, con middleware `auth`, `verified` y `permission:*`.
- Lógica de negocio en `app/Services/<Dominio>/`, no en el controlador (controladores delgados).
- Endpoint `/data` para JSON + vista Inertia para la página, siguiendo el patrón ya usado en el resto del proyecto.

## 2. Persistencia (regla crítica, no negociable)

- Toda información de negocio (tallas, curvas, despiece, consumos, cantidades, precios, etc.) debe existir en una **tabla relacional**, con **modelo Eloquent** y **migración**.
- Prohibido que un dato de negocio viva únicamente en el frontend, en `localStorage`, o en un campo JSON sin estructurar.
- Si encuentras código existente que viola esto, no lo repliques — señálalo y, si la tarea lo permite, migra ese dato a tabla relacional antes de continuar.

## 3. Auditoría

- Registrar el observer del modelo nuevo en `app/Providers/AppServiceProvider.php`.
- Definir `fillable`/`casts` en el modelo.
- Excluir campos de alto cambio (ruidosos) en `AuditLog::$auditIgnore` para no ensuciar el historial.

## 4. Frontend

- Tipos de dominio reutilizables en `resources/js/domain/<módulo>/types.ts`, exportados desde su `index.ts` — no declarar interfaces de dominio inline en páginas.
- Dialogs nuevos con el patrón `Dialog` + `FormDialogContent` + `FormHeader` + `DialogFooter`; no improvisar contenedores alternos.
- **Alertas obligatorias** en toda operación de crear/editar/eliminar: `useGlobalAlert()` + `AlertStack` en páginas, `AlertBox` local en dialogs. Nunca dejar al usuario sin saber si algo se guardó o falló.

## 5. Pruebas (regla nueva de este proyecto, más allá de lo que ya dice copilot-instructions.md)

- Ninguna funcionalidad se marca como lista sin al menos **una prueba de humo en Pest** que cubra el flujo principal.
- Esto es obligatorio incluso en módulos que hoy no tienen ningún test (Textil, Inventario, Compras) — cada funcionalidad nueva es la oportunidad de empezar a cubrirlos, no una excusa para seguir sin pruebas.
- Si vas a corregir un bug reportado, primero escribe (o confirma) una prueba que lo reproduzca antes de tocar el código de la corrección.

## 6. Cierre de QA

Antes de dar la tarea por terminada:

```
php artisan migrate:fresh --seed
vendor/bin/pest
npm run lint
npm run format:check
```

Si algo de esto falla, la tarea no está lista.
