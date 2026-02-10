# Copilot Instructions for soft_olamtex

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
- UI libraries in use: Headless UI, Radix Vue, Lucide; state via Pinia.
- Formatting & linting: `npm run format` for `resources/`, `npm run lint` uses ESLint + Prettier + Tailwind plugin.

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
- DB migration & seed: `php artisan migrate:fresh --seed`. Route binding and permissions require seeded data.
- Tests: Pest is configured; run `vendor/bin/pest` or `php artisan test`.
- Builds: `npm run build` (CSR) or `npm run build:ssr` for SSR bundles.

### Debugging Tips
- Check the authenticated user, roles y permisos via [routes/web.php#L30-L39](routes/web.php#L30-L39) (`GET /user`).
- Admin panel renders `Configuration/Index` at [routes/web.php#L78](routes/web.php#L78) for quick navigation.

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

### Naming & Structure
- Prefer Spanish route prefixes and names (e.g., `usuarios`, `gastos`, `inventario`, `kardex-telas`).
- Provide both the Inertia page endpoint (commonly `index`) and a `/data` JSON endpoint (commonly `getData`). Some modules add `indexView` for pages (see Product Operation List in [routes/web.php#L55-L72](routes/web.php#L55-L72)).

## Notes for Agents
- Respect existing patterns: route grouping, Spanish names, `/data` JSON endpoints, Inertia views.
- Prefer services for business logic; keep controllers lean.
- If you need to modify Postman pre/post scripts (`*-pre-*-pm-*`, `*-post-*-pm-*`), consult team rules; the referenced instruction files were not accessible from this workspace.

### Agent Playbook (Adding a Feature)
- Backend: Create controller under appropriate domain (e.g., `app/Http/Controllers/Inventory`), define routes with `Route::prefix()` + Spanish `name()`, include `auth`, `verified`, and `permission:*` middleware, and expose `/data` + page endpoints.
- Services: Put non-trivial logic in `app/Services/<Domain>` and call from controllers.
- Auditing: If new models are introduced, register observers in [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) and set `fillable`/`casts` in models; ignore noisy fields in [app/Models/AuditLog.php](app/Models/AuditLog.php).
- Frontend: Add Inertia Vue 3 TS components under `resources/js/<Domain>/...`, navigate via Ziggy route names, and keep names aligned with backend groups.
- QA: Seed the DB with `php artisan migrate:fresh --seed`, run `vendor/bin/pest`, and format/lint the `resources/` folder.

If any section is unclear or misses a key pattern, please point it out so we can refine these instructions.