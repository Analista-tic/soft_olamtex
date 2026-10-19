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

## 5.1 Cambios de reglas de validación (obligatorio/opcional, condicionales)

Una regla de negocio de "campo obligatorio" vive en varias capas a la vez. Antes de declarar el cambio hecho:

1. **Rastrea el campo en todas las capas** con un solo Grep por nombre de campo y por etiqueta visible (ej. `groups` y `Categoría proveedor/cliente`) sobre `app/` y `resources/js/`. Capas típicas: regla del backend (FormRequest/`rules()`), asterisco del formulario, lista de campos obligatorios del diálogo (`requiredFieldKeys` y similares), mensajes de error y servicios de importación.
2. **Cambia todas las capas en el mismo turno.** Un cambio en solo la regla del backend o solo el asterisco no cuenta como corregido.
3. **Busca otros consumidores del componente** (otros diálogos/páginas que lo importen) y confirma que heredan el comportamiento.
4. **Aplica exactamente lo que el usuario eligió.** No amplíes el alcance (ej. otro tipo de contacto) ni lo reduzcas sin preguntar.
5. **No digas "corregido" sin evidencia.** Si no probaste el flujo real (navegador o prueba que ejercite el diálogo), dilo explícitamente en vez de afirmar que funciona. Si el usuario reporta que sigue fallando, el mensaje exacto que pegó es la pista: búscalo literalmente (Grep del texto) antes de cualquier otra cosa.

## 5.2 Formularios y dialogs: validación visible (obligatorio al crear o editar cualquier formulario)

Aplica la regla "validación visible en formularios y dialogs" de `.github/copilot-instructions.md`. Antes de dar por terminado cualquier cambio en un formulario:

1. **Lista los campos obligatorios** (los del `handleSubmit`/`requiredErrors`, los del backend y los dinámicos como campos adicionales o filas repetibles) y verifica que **cada uno** tiene: asterisco en la etiqueta, borde rojo + "Requerido" tras intentar guardar, y se limpia al completarlo.
2. **Una sola función decide "falta"** y la usan el mensaje general y el template. Si encuentras la condición duplicada, unifícala.
3. **Revisa el componente de cada campo**: si no soporta `invalid`/`error`, agrégalo al componente compartido y confirma que los demás usos no cambian.
4. **Vacío ≠ 0** en campos numéricos.
5. **Edición de registros antiguos:** abre mentalmente (o con datos reales de la BD de desarrollo, solo lectura) un registro existente y comprueba que ningún campo obligatorio vacío queda sin poder corregirse. Si el mensaje de error dice "falta X", el usuario debe ver X marcado y editable.
6. **Importación masiva:** si el formulario exige el campo, la importación (Excel) debe exigirlo igual.
7. **Evidencia:** `vue-tsc` sin errores en los archivos tocados + prueba Pest del backend; si no se probó en el navegador, decirlo.

## 6. Cierre de QA

Antes de dar la tarea por terminada:

```
php artisan migrate:fresh --seed
vendor/bin/pest
npm run lint
npm run format:check
```

Si algo de esto falla, la tarea no está lista.
