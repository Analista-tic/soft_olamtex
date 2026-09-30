# REGLA CRÍTICA SOBRE PERSISTENCIA DE INFORMACIÓN DE NEGOCIO

**Está terminantemente prohibido guardar información relevante de negocio (como unidades por talla, curva, piezas de despiece, consumos, etc.) únicamente en el frontend, en archivos JSON, o en campos no estructurados.**

**Toda información que afecte procesos, reportes, inventario, costos, o que sea necesaria para auditoría o trazabilidad, DEBE existir y persistirse en una tabla relacional en la base de datos.**

- No se permite implementar features donde el desglose de unidades por talla/curva de corte/despiece solo viva en el frontend o en campos JSON.
- Todo cambio que implique captura, edición o consulta de datos de negocio debe reflejarse en una tabla SQL normalizada, con modelo Eloquent y migración correspondiente.
- Los agentes (Copilot, Claude, PR reviewers) deben rechazar cualquier PR, commit o feature que no cumpla esta regla.
- Si se detecta lógica que solo persiste en frontend/JSON, debe migrarse a tabla relacional antes de continuar.

**Esta regla aplica a toda la base de código y a cualquier agente o asistente (Copilot, Claude, etc).**

# Copilot Instructions for soft_olamtex

## Aplicabilidad obligatoria
- **Todo código nuevo debe seguir estas instrucciones.** Son la referencia única para consistencia, depuración futura y mantenimiento.
- Al añadir o modificar features: respetar stack, patrones, convenciones de nombres y estructura descrita aquí. La desviación dificulta el debugging y la evolución del proyecto.
- Si un patrón no está documentado, replicar el más parecido existente en el código (controllers, services, rutas, componentes) y, si hace falta, proponer una actualización de este documento.

## Criterios de ingeniería (Laravel + Vue)
- **Enfoque:** Decidir como ingeniero de desarrollo especializado en Laravel y Vue aplicando mejores prácticas.
- **Cohesión de dominio:** Agrupar modelos y servicios por dominio de negocio, no por tipo técnico. Ejemplo: modelos de producción/diseño en `ProductSheet`, no en `Expenses` si el flujo es diseño-producto.
- **Alias conceptual vs implementación:** Si un concepto de documentación tiene equivalencia en el código (ej. `ProductionOrder` = `Programmer`), usar el modelo existente y adaptar el diseño en lugar de duplicar entidades.
- **Namespaces:** Ubicar modelos donde tenga sentido el flujo: `App\Models\ProductSheet\ProductionOrderOperation` cuando la orden/programación vive en ProductSheet (`Programmer`).
- **Convenciones Laravel:** Respectar fillable, casts, relaciones `belongsTo`/`hasMany`, soft deletes cuando aplique, FormRequests para validación.
- **Convenciones Vue/Inertia:** TypeScript-first, composables reutilizables, componentes bajo `resources/js/pages/<Dominio>/`, formularios con validación explícita.

### Decisión documentada: Listados Operacionales (ProductionOrder)
- **ProductionOrder** en documentación = **Programmer** en código (`app/Models/ProductSheet/Programmer.php`). No crear modelo `ProductionOrder` nuevo.
- **ProductionOrderOperation:** Modelo `App\Models\ProductSheet\ProductionOrderOperation`, tabla `production_order_operations`, FK `programmer_id` (referencia a `programmers`). Mantener nombre de dominio "ProductionOrderOperation" para claridad conceptual; la FK apunta a Programmer.
- **ProductionOrderService** (o equivalente): En `App\Services\ProductSheet\`, operar sobre `Programmer` para crear snapshots congelados (`ProductionOrderOperation`).

## Stack & Entry Points
- Backend: Laravel 12 (PHP ^8.2) with Spatie Permissions, Inertia-Laravel, DOMPDF, PhpSpreadsheet, Maatwebsite Excel.
- Frontend: Vue 3 + Inertia + Vite 6 + Tailwind; TypeScript-first. Entry files at [resources/js/app.ts](resources/js/app.ts) and SSR at [resources/js/ssr.ts](resources/js/ssr.ts). Aliases: `@` → `resources/js`, `ziggy-js` → `vendor/tightenco/ziggy` (see [vite.config.ts](vite.config.ts)).
- Routing: Ziggy is available for client-side route names; Inertia pages are rendered from controllers using `Inertia::render`.

## Architecture Overview
- Domain modules under [app/Http/Controllers](app/Http/Controllers): `ProductSheet`, `Inventory`, `Expenses`, `Settings`, `Common`. Each module exposes RESTful controllers and `Route::prefix()` groups (see [routes/web.php](routes/web.php)).
- Services live under [app/Services](app/Services) grouped by domain; use services in controllers for business logic.
- Auditing: An `AuditObserver` logs `created/updated/deleted` for many models registered in [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php). Audit entries are persisted by `AuditLogService` to [app/Models/AuditLog.php](app/Models/AuditLog.php). Add `observe(AuditObserver::class)` in the provider when introducing new auditable models.
- Authorization: Fine-grained role/permission checks via middleware like `permission:ver usuarios` on routes.

## Backend Patterns
- Routes are grouped with Spanish prefixes and names, e.g., `Route::prefix('gastos')->name('gastos.')` and expose both page views (`Inertia::render`) and data endpoints (`/data`). Follow this pattern when adding new features.
- Route model binding is used (e.g., `Route::model('purchaseDocument', PurchaseDocument::class)` in the provider).
- Controllers generally provide standard CRUD: `index`, `getData`, `store`, `show`, `update`, `destroy`, plus module-specific actions (e.g., `toggleStatus`, `justify`, `convertToInvoice`). Mirror naming and HTTP verbs accordingly.
- Auditing context includes `user`, `ip_address`, and `user_agent` gathered in [app/Observers/AuditObserver.php](app/Observers/AuditObserver.php). Avoid logging noisy fields by adding to `AuditLog::$auditIgnore`.

### Quick Examples
- Expenses group: `gastos/ordenes-compra` with actions like [convert-to-invoice](routes/web.php#L178) and sending PDFs [enviar-correo-pdf](routes/web.php#L177).
- Inventory items: endpoints for [bulk upload](routes/web.php#L144), [download template](routes/web.php#L140), [export Excel](routes/web.php#L141), and [toggle status](routes/web.php#L151).
- Taxes: `impuestos/{id}/toggle-status` action at [routes/web.php#L221](routes/web.php#L221).
- Kardex telas: nested reception flows ([approve](routes/web.php#L245), [reject](routes/web.php#L246)) under `kardex-telas/recepcion`.

## Frontend Patterns
- Inertia pages map to route targets; create Vue 3 TS components under `resources/js` aligned to route names (e.g., `Inventory/Articles/InventoryAdjustmentForm`).
- Use Ziggy for client-side navigation by route names; keep names consistent with backend groups.
- UI libraries in use: **shadcn/vue** (primary component library), Radix Vue, Lucide (icons), and Tailwind CSS for styling; state via Pinia.
- Formatting & linting: `npm run format` for `resources/`, `npm run lint` uses ESLint + Prettier + Tailwind plugin.

### Tipado TypeScript (obligatorio)
- Todo `type`/`interface` reutilizable o de dominio debe declararse en `resources/js/domain/<modulo>/types.ts` y exportarse desde su `index.ts`.
- Evitar declarar interfaces de dominio inline dentro de páginas/components (`resources/js/pages/**`) salvo tipos estrictamente locales y efímeros de UI.
- Mantener nomenclatura consistente por contexto (`<Entidad>Option`, `<Entidad>Payload`, `<Entidad>Dialog*`) para facilitar mapeo y depuración.

### shadcn/vue Components
- **Base components:** Import shadcn/vue components from `@/components/ui/*` (e.g., `Button`, `Dialog`, `Input`, `Select`, `DataTable`).
- **Component usage:** All components are pre-configured with Tailwind and Radix Vue. Customize via `cn()` utility for class merging and Tailwind variants.
- **Customization:** Components live in `resources/js/components/ui/` and can be modified directly to match project needs. Avoid modifying copies; update the source component.
- **Form handling:** Use shadcn/vue form components with Vue form libraries (e.g., `vee-validate`) for validation and binding. Example pattern:
  ```vue
  <template>
    <form @submit="onSubmit">
      <FormField v-slot="{ field }" name="email">
        <FormItem>
          <FormLabel>Email</FormLabel>
          <FormControl>
            <Input type="email" placeholder="email@example.com" v-bind="field" />
          </FormControl>
        </FormItem>
      </FormField>
      <Button type="submit">Submit</Button>
    </form>
  </template>
  ```
- **Data tables:** Use `DataTable` component for displaying tabular data with built-in sorting, filtering, and pagination. Pass column definitions and data rows.
- **Dialog/Modal:** Use `Dialog` for forms and confirmations. Keep modal logic scoped to Inertia page components.

### Regla obligatoria para dialogs
- **Todo dialog nuevo o modificado debe construirse con el patrón base del proyecto:** `Dialog` + `FormDialogContent` + `FormHeader` + `DialogFooter` cuando aplique.
- **No improvisar contenedores alternos** si ya existe el patrón `FormDialogContent`; este debe ser la base visual y estructural del modal.
- **Distribución proporcional del espacio:** al diseñar dialogs, repartir el ancho y alto de forma equilibrada entre formulario, tablas, paneles laterales, dibujo plano, ayudas visuales y acciones. Evitar paneles demasiado angostos o demasiado dominantes que recorten contenido principal.
- **Prioridad visual:** el contenido principal del flujo debe ocupar la mayor parte del espacio; contenido de apoyo (resúmenes, dibujos, colores, ayuda visual) debe ser más compacto y no competir con la captura de datos.
- **Responsive primero:** en desktop usar proporciones equilibradas; en pantallas menores apilar sin perder legibilidad ni ocultar campos importantes.
- **Consistencia de espaciado:** usar paddings, gaps y alturas visuales consistentes dentro del dialog; evitar bloques que se vean comprimidos o sobredimensionados frente a otros del mismo modal.

### Client Routing
- Use `route('usuarios.index')` etc. via Ziggy (`ziggy-js` alias). Keep names in [routes/web.php](routes/web.php) aligned with component locations under `resources/js`.

## Developer Workflows
- Dev (no SSR):
  ```sh
  composer run dev
  ```
  Runs `php artisan serve`, `php artisan queue:listen --tries=1`, and `npm run dev` concurrently.
- Dev with SSR:
  ```sh
  composer run dev:ssr
  ```
  Adds `php artisan inertia:start-ssr` and logs via `php artisan pail`.
- DB migration & seed: `php artisan migrate` y `php artisan db:seed --class=RolesAndPermissionsSeeder` (no destructivos). **NUNCA** `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe` sobre la BD de desarrollo: borra los datos de prueba. Solo con petición explícita del usuario.
- Tests: Pest usa `RefreshDatabase` y vacía la BD configurada en `.env`. **No ejecutar `vendor/bin/pest` ni `php artisan test`** salvo que el usuario lo pida explícitamente o exista una BD de pruebas aislada configurada.
- Builds: `npm run build` (CSR) or `npm run build:ssr` for SSR bundles.

### Depuración y mantenibilidad (facilitar debugging futuro)
- **Usuario y permisos:** Ver usuario autenticado, roles y permisos en [routes/web.php#L30-L39](routes/web.php#L30-L39) (`GET /user`). Panel de administración en `Configuration/Index` [routes/web.php#L78](routes/web.php#L78).
- **Lógica en servicios:** Mantener la lógica de negocio en `app/Services/` y controladores delgados; así los fallos se acotan a servicios concretos y son más fáciles de reproducir y depurar.
- **Nombres consistentes:** Usar prefijos y nombres en español en rutas, permisos y agrupaciones; facilita buscar en el código y en logs (ej. `gastos.ordenes-compra`, `inventario.articulos.data`).
- **Endpoints predecibles:** Siempre `/data` para JSON y vistas Inertia para páginas; mismo patrón en todos los módulos para localizar rápidamente controlador y método.
- **Auditoría:** Los cambios importantes quedan en `AuditLog`; evitar ruido con `AuditLog::$auditIgnore` en campos de alto churn. Al depurar, revisar `app/Models/AuditLog.php` y el contexto que guarda el observer.
- **Logs y errores:** En PHP usar `Log::` con contexto (ej. IDs, acción); en frontend no silenciar errores sin registro. Mensajes claros y en español cuando sea posible.
- **Tests:** Mantener y ampliar pruebas (Pest) en código nuevo; ayudan a reproducir bugs y a refactorizar sin romper comportamiento.

## Conventions & Examples
- Spanish naming for routes and permissions; keep `prefix`, `name`, and middleware consistent (see [routes/web.php](routes/web.php)).
- Data endpoints end with `/data` and return JSON; page endpoints render Inertia views.
- When adding a new model to be audited, register its observer in [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) and ensure fillable/cast arrays are set in the model (see [app/Models/AuditLog.php](app/Models/AuditLog.php)).
- Default string length is set globally in the provider (for MySQL index compatibility).

## Integration Points
- Exports/Imports: Use Maatwebsite Excel / PhpSpreadsheet from `Inventory` and related modules.
- PDFs & Mail: Generate PDFs via DOMPDF; email helpers exist in `Expenses` (e.g., purchase orders sending PDFs).
- Client routes: Use Ziggy; server routes defined in [routes/web.php](routes/web.php) and [routes/settings.php](routes/settings.php).

## Gotchas
- Permissions: Most routes are gated by `auth`, `verified`, and `permission:*`. Ensure proper middleware on new routes.
- SSR: Only needed for specific pages; wire via the existing SSR entry and composer script.
- Auditing noise: Add high-churn columns to `AuditLog::$auditIgnore` to reduce diff clutter.
- Consecutivos/identificadores de documentos: se asignan en el servidor al guardar con `App\Support\DocumentSequence` (contador atómico, dentro de la transacción de creación) y con índice único en BD; nunca `max(id)+1`, "el último por id" ni un número "sugerido" al abrir el formulario. Ver [docs/NUMERACION_Y_DUPLICADOS.md](../docs/NUMERACION_Y_DUPLICADOS.md).

### Naming & Structure
- Prefer Spanish route prefixes and names (e.g., `usuarios`, `gastos`, `inventario`, `kardex-telas`).
- Provide both the Inertia page endpoint (commonly `index`) and a `/data` JSON endpoint (commonly `getData`). Some modules add `indexView` for pages (see Product Operation List in [routes/web.php#L55-L72](routes/web.php#L55-L72)).

## Sistema de Alertas (Obligatorio en Todo Cambio)

### Regla Crítica
**Todo cambio que modifique estado, cree, edite, elimine o muestre un resultado DEBE incluir alertas.**

### Patrones de Alertas

**En Vistas (Pages):**
```typescript
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import AlertStack from '@/components/common/AlertStack.vue';

const { successMessage, errorMessage, errorType, success, error, warning, info, clear } = useGlobalAlert();

// En el template:
<AlertStack
    :success-message="successMessage"
    :error-message="errorMessage"
    :error-type="errorType"
    @close-success="clear"
    @close-error="clear"
/>

// En el código:
try {
    await guardar();
    success('Guardado correctamente');
} catch (err) {
    error('Error al guardar');
}
```

**En Dialogs:**
```typescript
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');

function setError(msg: string, type: 'error' | 'warning' | 'info' = 'error') {
    errorMessage.value = msg;
    errorType.value = type;
}

// En el template:
<div v-if="errorMessage" class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4 sm:px-0">
    <AlertBox
        :type="errorType"
        :title="errorType === 'warning' ? 'Advertencia' : 'Error'"
        :message="errorMessage"
        :auto-close-ms="errorType === 'error' ? 0 : 4000"
        class="pointer-events-auto"
        @close="errorMessage = ''"
    />
</div>
```

### Comportamiento de Alertas (IMPORTANTE)
- ✅ **success/warning/info:** Auto-cierre en 4 segundos, SIN botón X
- ✅ **error:** NO auto-cierre, CON botón X manual
- ✅ En dialogs: alertas nunca cierran el dialog al cerrase
- ❌ NUNCA: No mostrar alertas en cierre manual en dialogs

Ver documentación completa en [GUIA_ALERTAS.md](../docs/GUIA_ALERTAS.md).

## Convenciones de texto visible en UI (Obligatorio)

**Todo texto que ve el usuario final** (labels, `TableHead`, `DialogTitle`/títulos de página, texto de `Button`, `placeholder`, badges de estado, tooltips/`HelpTooltip`, mensajes de `useGlobalAlert`/`AlertBox`) sigue estas reglas. El objetivo es eliminar la mezcla actual de MAYÚSCULAS, Mayúscula Inicial En Cada Palabra y camelCase filtrado desde el backend.

### Capitalización: mayúscula inicial de oración (sentence case)
Solo la primera letra de la frase (y los nombres propios/siglas) va en mayúscula; el resto en minúsculas. En español **no existe el Title Case** de inglés.
- ✅ `Solicitud de compra y servicios`, `Nueva solicitud`, `Fecha de solicitud`, `Enviar a aprobación`
- ❌ `Solicitud De Compra Y Servicios` (Title Case), `SOLICITUD DE COMPRA` (mayúsculas sostenidas), `solicitud de Compra` (mezcla arbitraria)
- Excepción: acrónimos de negocio (`NIT`, `IVA`, `SOL-014`) y nombres propios (proveedor, persona, marca) conservan su forma natural.
- Ejemplo real a corregir cuando se toque el archivo: el `<h1>` de `PurchaseRequests/Index.vue` dice "Solicitud de Compra y Servicios" (Title Case) y debería ser "Solicitud de compra y servicios".

### Nunca mostrar el valor crudo del backend
- Prohibido pintar en el DOM un enum/estado en snake_case o minúsculas técnicas (`pending`, `provider_name`, `solicited_to`). Todo estado pasa por un diccionario de etiquetas en español (patrón `statusLabels` ya usado en `PurchaseRequests/Index.vue`) antes de mostrarse.
- Los labels de campo se redactan a mano en español con el casing de esta sección; no se derivan automáticamente del nombre de columna.

### Botones y acciones
Verbo en infinitivo o frase corta de acción, sentence case, sin punto final: `Guardar borrador`, `Guardar y enviar a aprobación`, `Eliminar solicitud`.

### Badges y mensajes
- Badges de estado: una palabra o frase corta en sentence case (`Pendiente`, `Aprobada`, `Rechazada`).
- Mensajes de alerta: oración completa en sentence case terminada en punto (`Solicitud eliminada correctamente.`). Tooltips/ayudas cortas sin verbo pueden omitir el punto.

### Ortografía y tildes (obligatorio)
- Toda cadena visible lleva tildes y ortografía correcta: `número` (no `numero`), `descripción` (no `descripcion`), `aprobación` (no `aprobacion`), uso correcto de `ñ`.
- Antes de cerrar una tarea que agregue o toque texto visible, revisar ortografía/tildes de cada cadena nueva o modificada.
- Un mismo concepto usa siempre el mismo término en todo el proyecto (ej. siempre "solicitud de compra"; no alternar con "solicitud de compras" o "pedido de compra" para la misma entidad).

### Formato numérico (es-CO)
- En la interfaz, el separador decimal es la **coma** y el de miles el punto (`50,25`; `$ 1.250.000`). Usar `toLocaleString('es-CO', …)` o los helpers de `@/lib/decimalFormat` (`formatDecimalInput`, `parseDecimalInput`, `toApiDecimal`).
- API, base de datos y validaciones backend usan **punto** decimal. Los campos que el usuario ve con coma se convierten con `toApiDecimal` justo antes de enviar.

### Alcance de aplicación
- Al modificar un archivo `.vue`/Blade/PHP por cualquier motivo, corregir de paso el texto visible de ese archivo que no cumpla esta convención. No se exige un barrido completo del proyecto solo por esta regla; se corrige de forma incremental, archivo por archivo, a medida que se toca cada uno.

## Optimización de tokens (obligatorio)
- Leer solo archivos mencionados en la tarea.
- No leer `node_modules/`, `dist/`, `.git/`, `build/`, `coverage/` salvo instrucción explícita.
- Aplicar el mínimo de cambios, idealmente en una sola operación por archivo.
- Evitar relecturas innecesarias y búsquedas amplias cuando ya exista contexto suficiente.
- En tareas complejas, esperar pasos numerados del usuario antes de ejecutar.
- Responder corto, directo y sin contexto redundante.

## Notes for Agents
- **GitHub Copilot (inicio de sesión/chat):** al iniciar cada sesión o chat, confirmar estas reglas en 1 línea y operar en modo token-eficiente.
- **El código nuevo debe aplicar estas instrucciones.** Respetar patrones existentes (agrupación de rutas, nombres en español, endpoints `/data`, vistas Inertia, servicios para lógica de negocio). La consistencia es la base para depurar y mantener el proyecto.
- **Criterios de ingeniería:** Actuar como ingeniero de desarrollo especializado en Laravel + Vue. En decisiones de diseño (namespaces, modelos, servicios), aplicar cohesión de dominio, reutilizar entidades existentes cuando el concepto coincida, y documentar aquí decisiones no triviales para futura referencia.
- Preferir siempre servicios para lógica de negocio; mantener controladores delgados.
- Si hay que modificar scripts Postman (`*-pre-*-pm-*`, `*-post-*-pm-*`), consultar reglas del equipo.

### Agent Playbook (Adding a Feature)
- **Backend:** Crear controlador en el dominio adecuado (ej. `app/Http/Controllers/Inventory`), definir rutas con `Route::prefix()` y `name()` en español, middleware `auth`, `verified` y `permission:*`, y exponer endpoint `/data` y endpoints de página.
- **Services:** Poner lógica no trivial en `app/Services/<Domain>` e invocarla desde el controlador.
- **Auditing:** Si se añaden modelos nuevos, registrar observers en [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php), definir `fillable`/`casts` en el modelo y excluir campos ruidosos en [app/Models/AuditLog.php](app/Models/AuditLog.php).
- **Frontend:** Añadir componentes Vue 3 + TS bajo `resources/js/<Domain>/...`, navegar con nombres de ruta Ziggy y mantener nombres alineados con los grupos del backend. **IMPORTANTE:** Siempre incluir alertas para operaciones (crear, editar, eliminar, guardar) usando `useGlobalAlert()` en páginas y `AlertBox` locale en dialogs.
- **Dialogs:** Cuando el feature use modal, construirlo con `FormDialogContent` y mantener una distribución proporcional del espacio: formulario principal dominante, paneles auxiliares compactos, y layout claro para lectura/captura.
- **Alertas:** Toda operación debe mostrar feedback: success para operaciones exitosas, error para fallos, warning para advertencias. Nunca dejar usuarios sin saber si algo se guardó o falló.
- **QA:** Ejecutar `php artisan migrate` (nunca `migrate:fresh` sobre la BD de desarrollo) y format/lint en `resources/`. Los tests Pest solo se ejecutan con BD de pruebas aislada o si el usuario lo pide. Todo código nuevo debe ser depurable siguiendo los patrones de este documento.

Si alguna sección no queda clara o falta un patrón importante, indicarlo para refinar estas instrucciones.