
Documentación Técnica y Funcional
Sistema ERP Olamtex
Versión del documento: 0.1Autor(a): Luisa Fernanda Ramirez Osorio.
Fecha: 30/06/2026
Tabla de Contenido
Tabla de Contenido .................................................................................................................... 2

1. Arquitectura General .............................................................................................................. 4
   1.1 Resumen del sistema ....................................................................................................... 4
   1.2 Stack tecnológico ............................................................................................................. 5
   1.3 Diagrama de arquitectura ................................................................................................. 7
   1.4 Estructura de carpetas ...................................................................................................... 8
   1.5 Entornos ........................................................................................................................... 9
   1.6 Diagrama de despliegue (Deployment) ............................................................................13
2. Instalación y Puesta en Marcha ............................................................................................15
   2.1 Requisitos previos ...........................................................................................................15
   2.2 Pasos de instalación ........................................................................................................15
   2.3 Variables de entorno .......................................................................................................16
   2.4 Problemas comunes ........................................................................................................17
3. Modelo de Datos ...................................................................................................................20
   3.1 Diagrama entidad-relación ...............................................................................................20
   3.2 Tablas principales ............................................................................................................28
   3.3 Relaciones clave entre módulos ......................................................................................29
   3.4 Diagrama de clases (UML) ..............................................................................................29
4. Módulos Funcionales ............................................................................................................31
   4.1 [Nombre del módulo, ej. Ventas] ......................................................................................31
   4.1.1 Propósito de negocio ................................................................................................31
   4.1.2 Diagrama de casos de uso (UML) .............................................................................31
   4.1.3 Flujo principal (diagrama de actividades) ..................................................................32
   4.1.4 Diagrama de estados ................................................................................................32
   4.1.5 Reglas de negocio importantes .................................................................................33
   4.1.6 Pantallas / endpoints relevantes ................................................................................33
   4.1.7 Integraciones externas ..............................................................................................34
   4.2 [Siguiente módulo] ...........................................................................................................34
5. Documentación Técnica de Código .......................................................................................78
   5.1 Convenciones de código .................................................................................................78
   5.2 Patrones de diseño identificados .....................................................................................80
   5.3 Diagramas de secuencia .................................................................................................82
   5.4 Integraciones con sistemas externos ...............................................................................85
   5.5 Deuda técnica conocida ..................................................................................................85
   5.6 Glosario de términos del negocio ....................................................................................85
6. Arquitectura General
   Objetivo: dar una visión de alto nivel del sistema para que cualquier persona nueva entienda “el
   mapa” antes de entrar en detalle.
   1.1 Resumen del sistema
   Sistema ERP diseñado para empresas del sector manufactura textil y confección. Centraliza y
   digitaliza los procesos operativos, productivos y financieros de una empresa que fabrica prendas
   de vestir a partir de telas y materias primas.
   Procesos de negocio que cubre:
   Área Procesos
   Producción Fichas de producto, fichas técnicas (tallas/telas/insumos), órdenes
   de producción, control de lotes (inspección, entrega, novedades).
   Textil  Gestión de rollos de tela, pedidos de tela, ajustes de inventario textil.
   Inventario Bodegas, movimientos de stock, ajustes, traslados, solicitudes
   internas.
   Ventas Cotizaciones, órdenes de venta, facturación, recibos de caja.
   Compras Solicitudes, órdenes de compra, recepción de mercancía, facturas
   de proveedor.
   Datos Maestros  Ítems, contactos (clientes/proveedores), categorías, parámetros del
   sistema.
   Reportes Reportes de producción, inventario y textil.

Usuarios principales:
Rol Áreas de la empresa que
lo usan
Acceso principal
 Admin  Gerencia, TICs,
Desarrollo Organizacional.
 Todo el sistema.
Producción  Producción, Diseño,
Comercial.
Fichas técnicas, control de lotes, programador,
inventario de telas, compras.
Corte Corte. Fichas técnicas, control de lotes, programador,
inventario de telas, compras.
Logística Logística Inventario completo de telas, solicitudes de
insumos, traslados de bodegas, órdenes de
compra
General Marketing, Recursos
Humanos.
Acceso mínimo: perfil, solicitudes de insumos,
solicitudes de compra propias
1.2 Stack tecnológico
Capa Tecnología / Versión Notas
 Lenguaje backend  PHP 8.2  Tipado estricto, enums, fibers
 Backend / Framework  Laravel 12.0  MVC + Eloquent ORM
 Frontend / Framework  Vue 3 3.5.13 + TypeScript
5.2
 Composition API, 
Puente SPA Inertia.js 2.0 Sin API REST separada; Laravel
sirve las páginas directamente.
Base de datos MySQL (puerto 3306) BD: soft_olamtex_db
Caché Database (Laravel Cache) Almacenado en tabla de BD; Redis
configurado, pero no activo por
defecto.
Cola de mensajes Database (Laravel Queue) Jobs en tabla jobs de MySQL
Sesiones Database Tabla sessions
Build / Bundler Vite 6.2 + laravel-vite
plugin 1.0
HMR en desarrollo
CSS Tailwind CSS 3.4 Utilidades + tailwindcss-animate
Sistema de diseño shadcn/ui (adaptado a
Vue)
Componentes construidos sobre
Reka UI + Radix Vue con Tailwind
Iconos Lucide Vue Next 0.513.0 Biblioteca de iconos open source,
consistente con shadcn
Gestión de estado Pinia 3.0 Store Vue 3
Formularios/Validación VeeValidate 4.15 + Zod
3.24
Validación reactiva con esquemas
Tablas TanStack Vue Table 8.21 Tablas con
sorting/filtering/paginación
Permisos spatie/laravel-permission
6.16
Roles y permisos granulares
Exportación PDF barryvdh/laravel-dompdf
3.1
Generación de PDFs server-side
Exportación Excel phpoffice/phpspreadsheet
5.5
Importación/exportación de hojas
de cálculo
Rutas JS Ziggy 2.4 Rutas Laravel disponibles en Vue
Servidor web PHP built-in / Apache o
Nginx
En desarrollo: php artisan serve
Auditoría
Notificaciones
Custom AuditLogService
Laravel Notifications (canal
database)
Desarrollado a medida; registra
cambios en modelos en tabla
audit_logs
Notificaciones internas en tiempo
real almacenadas en BD
HTTP Client (frontend) Axios 1.15.2
Utilidades de fecha
Peticiones HTTP desde Vue
date-fns 4.1.0
Formateo y manipulación de fechas
en frontend
Composables Vue
VueUse 12.8.2
Utilidades reactivas (intersection
observer, storage, etc.)
Testing
PestPHP 3.7 + Laravel
Dusk 8.5
Tests unitarios/funcionales (Pest) y
E2E en navegador (Dusk)
Calidad de código
Laravel Pint + ESLint 9 +
Prettier 3.4
Formateo PHP (Pint) y JS/TS
(ESLint + Prettier)
Generación de rutas
JS
Ziggy 2.4
Rutas de Laravel disponibles en
Vue como funciones JS
Entorno de desarrollo
Concurrently 9.0.1
npx concurrently "php artisan serve"
"php artisan queue:listen --tries=1"
"npm run dev"
1.2
Diagrama de arquitectura

1.4 Estructura de carpetas
Backend (app/)
Carpeta / Módulo Responsabilidad
app/Http/Controllers/ Controladores organizados por módulos: Auth, Expenses, Finance, Income, Inventory, MasterData,
Production, Reports, Settings, Textil. También StorageFileController.php para manejo de archivos
app/Http/Controllers/Concerns/ Traits reutilizables entre controladores: HandlesValidationErrors.php para manejo unificado de
errores de validación
app/Http/Requests/ Validaciones de formulario por módulo:
 Auth, Finance, Inventory, MasterData, ProductSheet, Production, Settings, Textil.
app/Http/Resources/ Transformadores de respuesta Eloquent → JSON para: Inventory, Production, Textil.
app/Models/ Modelos Eloquent por
módulo: Expenses, Finance, Income, Inventory, MasterData, Production, Settings, Textil.
app/Models/Concerns/ Trait HasAuditLabel.php: define cómo se describe un modelo en el log de auditoría
app/Services/ Lógica de negocio separada del controlador, por módulo:
 Audit, Expenses, Finance, Income, Inventory, MasterData, Production, Reports, Settings, Textil.
app/Services/Audit/ AuditLogService.php: registra creación, edición y eliminación de registros en tabla audit_logs
app/Enums/ Enumeraciones PHP tipadas: estados de órdenes de compra, facturas, solicitudes.
Subcarpetas Finance/ e Income/ con estados de documentos financieros y de venta.
app/Notifications/ Notificaciones internas (canal database): novedades de lote, solicitudes de compra, facturas, órdenes
de compra
app/Mail/ Correos enviables: PurchaseInvoiceMail.php y PurchaseOrderMail.php
app/Console/Commands/
Comandos Artisan personalizados: reconciliación de inventario de telas, cierre de período, generación
de plantillas, deduplicación de operaciones, entre otros
app/Observers/Textil/
Observadores de modelos de rollos de tela: reaccionan a eventos Eloquent (created, updated,
deleted)
app/Helpers/
Utilidades PHP globales: MonthLabel.php, NitHelper.php, ScopeHelper.php, SeasonParser.php
app/Support/
Clases de soporte: ModelUppercaseNormalizer.php y TextNormalizer.php para normalizar texto en
modelos
app/Rules/
Regla de validación personalizada: ValidCatalogOption.php
app/Contracts/
Interfaz HasAuditLabel.php: contrato que deben implementar los modelos auditables.
app/Providers/
AppServiceProvider.php: registro de bindings, observers y configuración del framework.
Rutas (routes/)
Archivo
Responsabilidad
web.php
Punto de entrada principal de rutas.
auth.php
Rutas de autenticación.
production.php
Rutas del módulo Producción.
inventory.php
Rutas del módulo Inventario.
expenses.php
Rutas del módulo Compras/Gastos.
income.php
Rutas del módulo Ventas/Ingresos.
finance.php
Rutas del módulo Financiero.
textil.php
Rutas del módulo Textil.
master-data.php Rutas de Datos Maestros.
settings.php Rutas de Configuración.
reports.php Rutas de Reportes.

Frontend (rosources/js/ )
Carpeta / Módulo Responsabilidad
pages/ Páginas Vue por módulo: Production, Inventory, Income, Expenses, Finance, Textil, MasterData,
Configuration, Reports, Audit, Operations.
domain/ Lógica de dominio del frontend por módulo. Cada módulo contiene: types.ts, dto.ts, mappers.ts, index.ts.
Módulos: Production, Textil, MasterData, Income, Expenses, Finance, inventory, Settings, auth, shared.
domain/shared/ Tipos globales compartidos: types.ts, dto.ts, mappers.ts, definiciones TypeScript (globals.d.ts, vue
shims.d.ts, ziggy.d.ts)
components/common/ Componentes Vue reutilizables del
proyecto: ConfirmDialog, StatusBadge, ServerPagination, CreateButton, DeleteButton, TableActionsMenu,
entre otros.
components/ui/ Componentes de shadcn/ui: button, dialog, table, select, input, form, tabs, sidebar, etc.
composables/ Composables Vue reutilizables: manejo de alertas, notificaciones, búsqueda de ítems, formularios de
programador, ficha técnica, comprobante de tesorería, entre otros.
composables/Income/ Composable específico del módulo Ingresos: useIncomeDocument.ts.
layouts/ Layouts base: app/ (usuario autenticado), auth/ (login/registro), settings/ (configuración de cuenta).
lib/ Utilidades JS/TS: utils.ts (clases Tailwind), date.ts, colorUtils.ts, taxes.ts, permissionModules.ts, ui-patterns.ts,
entre otros.
data/
Datos estáticos: colombia.json (ciudades/departamentos), unitMeasures.json (unidades de
medida), footerNavItems.ts
Base de datos (database/ )
Carpeta / Módulo
Responsabilidad
migrations/
Historial de cambios del esquema de BD. Control de versiones de la estructura de tablas.
seeders/
Datos iniciales: roles, permisos, usuarios, catálogos, datos de telas, operaciones, bodegas.
factories/
Fábricas para datos de
prueba: UserFactory, ContactFactory, PurchaseDocumentFactory, PurchaseRequestFactory
Almacenamiento (storage/app/public/ )
Carpeta
Responsabilidad
company-logos/Logos de la empresa cargados desde Configuración.
items/
Imágenes de ítems/productos.
product_sheets/
Archivos de fichas de producto.
purchase-invoices/ Documentos adjuntos de facturas de compra.
purchase-requests/ Archivos adjuntos de solicitudes de compra.
inventory-requests/ Adjuntos de solicitudes de insumos.
supply-images/
Imágenes de insumos.
Documentación (docs/ )
Responsabilidad
Documentación técnica interna del proyecto: análisis de flujos, guías de módulos, estrategias de despliegue, auditoría de seguridad,
planes de implementación y roadmap de onboarding.
1.5 Entornos
Entorno
URL / Acceso
Propósito
Desarrollo local
http://127.0.0.1:8000/Producción
Hostinger — subdominio
configurado en hPanel
https://4u.manufacturasolamtex.com/
Desarrollo y pruebas del
programador. Se levanta
con composer dev (Laravel +
Vite HMR + Queue).
Entorno real usado por los
empleados de Olamtex
1.5.1 Detalle de despliegue por entorno.
Desarrollo local:
• APP_ENV=local / APP_DEBUG=true
• Base de datos: MySql local (soft_olamtex_db)
• Caché y sesión: tabla de DB.
• Assets: Vite en modo HMR (npm run dev)
• Comando: composer dev
Producción (Hostinger):
• APP_ENV=production / APP_DEBUG=false
• Servidor: Hostinger Business Web Hosting con soporte SSH• Acceso SSH: ssh -p 65002 u133998426@157.173.209.96
• PHP: 8.2 (php8.2 artisan)
• Assets: compilados localmente con npm run build y subido vía scp.
• Caché: archivo (CACHE_STORE=file) – recomendado para hosting compartido.
• Deploy manual: git pull + composer install + php8.2 artisan migrate –force +
php8.2 artisan optimize• Colas: procesadas por Cron Job en hPanel cada minuto (Schedule:run)
• No hay CI/CD automatizado – el despliegue es manual vía SSH1.6 Diagrama de despliegue (Deployment)
2. Instalación y Puesta en Marcha
Objetivo: que cualquier desarrollador(a) nuevo pueda levantar el proyecto en su máquina sin
ayuda externa.
2.1 Requisitos previos
Herramienta
Versión
mínima
Notas
PHP
8.2
Con extensiones: pdo_mysql, mbstring, openssl, tokenizer, xml,
ctype, json.
Composer
2.x
Gestor de dependencias PHP
Node.js
22.x
Requerido por @types/node ^22 en el proyecto.npm10.x
Incluido con Node.js
MySql
8.x
Base de datos principal
Git
Cualquier
versión
recientehttps://github.com/Analista-tic/soft_olamtex.git
Nota: No se requiere Doker, Redis ni ningún servicio externo para desarrollo local. Caché,
sesiones, colas corren sobre MySql.2.2 Pasos de instalación

1. Clonar el repositorio:o git clone https://github.com/Analista-tic/soft_olamtex.gito cd soft_olamtex
2. Instalar dependencias:
   o #Dependencias PHP
   Composer install
   o Dependencias JavaScript
   npm install
3. Configurar variables de entorno:
   o cp .env.example .env
   php artisan key:generate
   Editar .env con los datos locales:
   APP_NAME="OlamTex ERP"
   APP_URL=http://localhost
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=soft_olamtex_db
   DB_USERNAME=root
   DB_PASSWORD=tu_password_local
   Nota:
   Las
   demás
   variables
   (CACHE_STORE, QUEUE_CONNECTION,
   SESSION_DRIVER) ya están configuradas en .env.example para funcionar con base de
   datos sin configuración adicional.
4. Ejecutar migraciones / cargar datos iniciales:Crear la base de datos en MySQL:
   o CREATE DATABASE soft_olamtex_db CHARACTER SET utf8mb4 COLLATE
   utf8mb4_unicode_ci;
   Ejecutar migraciones con datos de desarrollo (incluye datos de ejemplo):
   o php artisan migrate:fresh --seed --seeder=DevelopmentSeeder
   Esto carga automáticamente:
   o Roles y permisos (admin, produccion, corte, contabilidad, logistica, general)
   o Usuarios de ejemplo por área
   o Categorías, impuestos, bodegas, plan de cuentas
   o Operaciones de producción
   o Datos de ejemplo: contactos, telas, rollos, compras, fichas técnicas, lotes
5. Levantar el servidor / aplicación:
   composer dev
   Este comando levanta en paralelo:
   o php artisan serve → backend en http://localhost:8000
   o npm run dev → Vite HMR para frontend
   o php artisan queue:listen → procesador de colas
   Abrir en el navegador: http://localhost:8000
   Credenciales de acceso iniciales (creadas por el seeder):
   o Email: superadmin@softolamtex.local
   o Password: SoftOlamtex2026!
   2.3 Variables de entorno
   Aplicación
   Variable
   Descripción
   APP_NAME
   Nombre de la aplicación
   Ejemplo (sin datos
   reales)
   APP_ENV
   Olamtex
   Entorno de ejecución
   Production
   APP_KEY Clave de cifrado de la app
   (generada con artisan
   key:generate)
   base64:xxxxx...
   APP_DEBUG Muestra errores detallados False
   APP_URL URL base pública de la
   aplicación
   https://mi-dominio.com
   APP_LOCALE Idioma principal Es
   APP_FALLBACK_LOCALE

Idioma de respaldo En
Base de datos
Variable Descripción Ejemplo (sin datos reales)
 DB_CONNECTION Motor de base de datos mysql
DB_HOST Host del servidor de base de
datos
127.0.0.1
DB_PORT Puerto de conexión 3306
DB_DATABASE Nombre de la base de datos soft_olamtex_db
DB_USERNAME Usuario de la base de datos db_user
DB_PASSWORD Contraseña de la base de datos
Sesión y caché
Variable Descripción Ejemplo (sin datos reales)
 SESSION_DRIVER Motor de sesiones Database
 SESSION_LIFETIME Duración de sesión en minutos  120
CACHE_STORE Motor de caché Database
QUEUE_CONNECTION Motor de colas de trabajo Database
Correo electrónico
Variable Descripción Ejemplo (sin datos reales)
MAIL_MAILER Driver de envío de correo Smtp
MAIL_HOST Servidor SMTP smtp.hostinger.com
MAIL_PORT Puerto SMTP 465
MAIL_USERNAME Usuario de la cuenta de correo correo@mi-dominio.com
MAIL_PASSWORD Contraseña del correo
MAIL_FROM_ADDRESS Dirección remitente no-reply@mi-dominio.com
MAIL_FROM_NAME Nombre remitente Olamtex
Logs
Variable Descripción Ejemplo (sin datos reales)
LOG_CHANNEL Canal de logs activo Stack
LOG_LEVEL  Nivel mínimo de log error

2.4 Problemas comunes
Durante la instalación local
Error Causa Solución
No application encryption key
has been specified
Falta el APP_KEY en .env Ejecutar php artisan key:generate
SQLSTATE: Access denied
for user
Credenciales de BD
incorrectas en .env
Verificar DB_USERNAME,
DB_PASSWORD y DB_HOST
Class not found al levantar la
app
Dependencias de
Composer no instaladas
Ejecutar composer install
Página en blanco o error 500
sin mensaje
APP_DEBUG=false oculta
el error
Cambiar
a APP_DEBUG=true temporalmente
y revisar storage/logs/laravel.log
VITE_APP_NAME no
resuelve / assets no cargan
Assets de frontend no
compilados
Ejecutar npm install && npm run
build
Tablas no existen
/ SQLSTATE: Table doesn't
exist
Migraciones no
ejecutadas
Ejecutar php artisan migrate
Error de permisos
en storage/ o bootstrap/cache/
Permisos incorrectos en
carpetas de escritura
Ejecutar chmod -R 775 storage
bootstrap/cache

Durante el despliegue en Hostinger
Error Causa Solución
Error 500 tras git pull Migraciones nuevas sin
ejecutar en producción
Ejecutar php8.2 artisan migrate vía
SSH
Error 500 en rutas específicas
(422 o 500 en consola)
Columnas nuevas en BD
que aún no existen
Verificar migraciones pendientes
con php8.2 artisan migrate:status
Sesiones expiradas
constantemente
SESSION_DOMAIN mal
configurado o APP_KEY
diferente al de producción
Verificar que el .env de producción
tenga el APP_KEY correcto
Assets no actualizados tras el
deploy
El npm run build no se
ejecutó antes del git push
Compilar con npm run
build localmente y subir la
carpeta public/build/
Caché de rutas/vistas
desactualizada
Caché vieja después de
cambios
Ejecutar php8.2 artisan
optimize:clear

En tiempo de ejecución
Error Causa Solución
Correos no se envían MAIL_MAILER=log en
producción
Configurar SMTP real de Hostinger
en el .env de producción
Trabajos en cola no se
procesan
El queue:work no está
corriendo
Verificar el Cron Job en hPanel o
lanzar php8.2 artisan queue:work
Unchecked
runtime.lastError en consola
del navegador
Error de extensión de
Chrome (no es del
sistema)Ignorar — no afecta la aplicación

3. Modelo de Datos
   Objetivo: entender cómo está organizada la información y cómo se relacionan los módulos
   entre sí.
   3.1 Diagrama entidad-relación
   Módulo 1 – Usuarios y Permisos
   Nota: La tabla user  se relaciona con model_has_roles y model_has_permissions mediante
   relación polimórfica (campos model_type + model_id). Esta relación no aparece como línea
   porque no existe foreign key en la base de datos – es gestionada por la capa de aplicación
   (Spatie Permission).
   Relaciones:
   • Un usuario puede tener asignados uno o varios roles: por cada rol que se le asigna, se
   crea un registro en model_has_roles con el model_id igual al id del usuario y model_type
   igual a App\Models\User.
   • Un usuario puede tener permisos asignados directamente, de forma independiente a su
   rol. La tabla model_has_permissions actúa como tabla pivote entre el usuario y el permiso
   específico.
   • Un rol agrupa uno o varios permisos. La tabla role_has_permissions define qué acciones
   puede realizar cada rol dentro del sistema.
   • Un usuario puede tener asignados uno o varios roles: por cada rol que se le asigna, se
   crea un registro en model_has_roles con el model_id igual al id del usuario y model_type
   igual a App\Models\User.
   • Un usuario también puede tener permisos asignados directamente sin necesidad de un
   rol: por cada permiso directo, se crea un registro en model_has_permissions con el mismo
   esquema de model_id y model_type
   Módulo 2 – Catálogos generales
   Nota: Las tablas sin líneas de relación (bank_accounts, collections, warehouses, contacts,
   retentions, operation_actions, operation_garment_parts) son tablas maestras de catálogo. Sus
   relaciones existen hacia otros módulos del sistema (Compras, Ventas, Inventario) y se visualizan
   en los diagramas correspondientes. Se incluyen aquí porque pertenecen conceptualmente al
   núcleo de datos compartidos del sistema.
   Relaciones:
   • Cada operación pertenece a una sección operacional. La tabla operations almacena el
   section_id que referencia a operation_sections, indicando a qué tipo de proceso
   pertenece la operación (confección, preparación, terminación, etc.).
   • Una operación tiene asociada una máquina de referencia. La tabla operations guarda el
   machine_id_reference apuntando a la máquina con la que típicamente se ejecuta dicha
   operación.
   • Cada ítem del catálogo de productos puede tener un impuesto asociado. La tabla items
   almacena el tax_id que referencia al tipo de impuesto aplicable (IVA, etc.) definido en
   taxes.
   • Una bodega puede ser usada en muchos movimientos de inventario (relación visible en
   módulo 5)
   •  Un contacto puede estar en muchos documentos de compra o venta (relación visible en
   módulos 6 y 7)
   • Una cuenta bancaria puede estar en muchos pagos (relación visible en módulo 7)
   • Una retención puede aplicar a muchos documentos (relación visible en módulos 6 y 7)
   • Una colección puede agrupar muchas fichas de producto (relación visible en módulo 3)
   • Una acción puede estar en muchas operaciones (relación a nivel de aplicación)
   • Una parte de prenda puede estar en muchas operaciones (relación a nivel de aplicación)
   Módulo 3 – Fichas técnicas / Sábanas de productos
   Nota: Las tablas product_sheets y technical_sheets son las entidades centrales del módulo.
   technical_sheets agrupa toda la información técnica de una referencia: procesos, rollos, hilos,
   materiales, operaciones, tallas e imágenes de estampado — todas con relación directa hacia
   ella.
   product_sheets se conecta con product_sheet_cutting_pieces (piezas de corte por referencia).
   La tabla technical_sheet_supplies aparece sin línea porque su relación con technical_sheets se
   gestiona a nivel de aplicación sin foreign key definida en la base de datos.
   Relaciones:
   • Una ficha de producto (product_sheets) puede tener una ficha técnica asociada
   (technical_sheets). La ficha técnica es el documento detallado que describe cómo se
   construye el producto.
   • Una ficha de producto puede tener muchas piezas de corte definidas, pero cada pieza de
   corte pertenece a una sola ficha de producto.
   • Una ficha técnica puede tener muchos procesos de confección registrados, pero cada
   proceso pertenece a una sola ficha técnica.
   • Una ficha técnica puede tener muchos rollos de tela asociados, pero cada rollo pertenece
   a una sola ficha técnica.
   • Una ficha técnica puede tener muchos hilos definidos, pero cada hilo pertenece a una
   sola ficha técnica.
   • Una ficha técnica puede tener muchas materias primas registradas, pero cada materia
   prima pertenece a una sola ficha técnica.
   • Una ficha técnica puede tener muchas operaciones de confección definidas, pero cada
   operación pertenece a una sola ficha técnica.
   • Una ficha técnica puede tener muchas tallas registradas, pero cada talla pertenece a una
   sola ficha técnica.
   • Una ficha técnica puede tener muchas imágenes de estampado asociadas, pero cada
   imagen pertenece a una sola ficha técnica.
   • Sus insumos se relacionan con la ficha técnica a nivel de aplicación, sin foreign key
   definida en la base de datos.
   Módulo 4 – Listados operacionales
   Tabla central: product_sheet_operations registra cada operación asignada a una ficha de
   producto, incluyendo tiempos (SAM base, óptimo, productivo, real), factores de cálculo
   (eficiencia, suplemento de máquina, base de tela) y el SAM total resultante.
   product_sheet_operation_history guarda el historial de cambios de factores y SAM cada vez que
   una operación es modificada, permitiendo trazabilidad completa de los ajustes.
   audit_logs registra acciones generales del sistema (creación, edición, eliminación) de cualquier
   módulo. Aparece sin línea porque su relación con product_sheet_operations es polimórfica
   (auditable_type + auditable_id), no mediante foreign key directa.
   Relaciones:
   • Una operación del listado puede tener muchos registros de historial, pero cada registro
   de historial pertenece a una sola operación. Cada vez que se modifican los factores o el
   SAM de una operación, se genera un nuevo registro en el historial.
   • Una ficha de producto puede tener muchas operaciones registradas en su listado
   operacional, pero cada operación pertenece a una sola ficha de producto.
   • Una operación del catálogo puede aparecer en los listados operacionales de muchas
   fichas de producto, pero cada registro en el listado referencia a una sola operación del
   catálogo.
   • Una máquina puede estar asignada a muchas operaciones dentro de los listados, pero
   cada operación del listado tiene asignada una sola máquina.
   • Una sección operacional puede agrupar muchas operaciones en los listados, pero cada
   operación pertenece a una sola sección.
   Módulo 5 – Inventario
   Relaciones visibles:
   • inventory_requests → inventory_request_items: una solicitud de inventario contiene
   múltiples ítems solicitados.
   • inventory_adjustments → inventory_adjustment_lines: un ajuste de inventario contiene
   múltiples líneas de detalle.
   Tablas sin línea — razón: Sus relaciones apuntan a tablas de otros módulos (catálogos,
   compras) que no están incluidas en este diagrama, o se gestionan a nivel de aplicación sin
   foreign key en la BD:
   • warehouse_items — stock actual por bodega (se relaciona con warehouses e items del
   módulo de catálogos)
   • inventory_movements — registro de cada entrada/salida de inventario
   • warehouse_transfers — traslados entre bodegas
   • inventory_period_locks — bloqueo de períodos contables para evitar modificaciones
   retroactivas
   • fabric_rolls — rollos de tela disponibles en bodega
   Relaciones:
   • Una solicitud de inventario puede contener muchos ítems solicitados, pero cada ítem
   solicitado pertenece a una sola solicitud de inventario.
   • Un ajuste de inventario puede tener muchas líneas de detalle, pero cada línea de ajuste
   pertenece a un solo ajuste de inventario.
   • Una bodega puede almacenar muchos ítems, pero cada registro de stock pertenece a
   una sola bodega. (relación visible desde módulo de catálogos)
   • Un ítem del catálogo puede estar almacenado en muchas bodegas, pero cada registro de
   stock referencia a un solo ítem. (relación visible desde módulo de catálogos)
   • Un ítem en bodega puede tener muchos movimientos de entrada y salida registrados,
   pero cada movimiento pertenece a un solo ítem en una bodega específica.
   • Una bodega puede ser origen o destino de muchos traslados, pero cada traslado tiene
   definida una sola bodega de origen y una sola bodega de destino.
   • Un traslado entre bodegas puede contener muchos ítems trasladados, pero cada ítem
   trasladado pertenece a un solo traslado.
   • Registra bloqueos de períodos contables de forma independiente. No tiene relación
   directa con otras tablas del módulo — actúa como control de fechas a nivel de sistema.
   • Registra los rollos de tela disponibles en bodega. Sus relaciones apuntan hacia
   warehouses e items del módulo de catálogos.
   Módulo 6 – Compras
   Módulo de Compras — flujo secuencial:
   purchase_requests → purchase_documents: una solicitud de compra se convierte en un
   documento de compra (orden o factura de proveedor).
   purchase_documents es la tabla central y se ramifica en:
   • purchase_document_items: detalle de los ítems comprados (cantidad, precio, impuestos
   por línea)
   • purchase_payments: pagos registrados contra ese documento de compra
   Relaciones:
   • Una solicitud de compra puede dar origen a uno o varios documentos de compra, pero
   cada documento de compra puede estar asociado a una sola solicitud de compra.
   • Un documento de compra puede contener muchos ítems detallados, pero cada ítem de
   detalle pertenece a un solo documento de compra.
   • Un documento de compra puede tener registrados muchos pagos parciales o totales,
   pero cada pago está asociado a un solo documento de compra.
   • Un proveedor (contacto) puede tener muchos documentos de compra asociados, pero
   cada documento de compra pertenece a un solo proveedor. (relación visible desde
   módulo de catálogos)
   • Un ítem del catálogo puede aparecer en muchos documentos de compra, pero cada
   línea de detalle referencia a un solo ítem. (relación visible desde módulo de catálogos)
   • Una cuenta bancaria puede estar asociada a muchos pagos de compra, pero cada pago
   registra una sola cuenta bancaria. (relación visible desde módulo de catálogos)
   Módulo 7 – Ventas y finanzas
   Módulo de Ventas y Finanzas — dos bloques principales:
   Bloque Ventas: sale_documents es la tabla central y se ramifica en:
   • sale_document_items: detalle de los productos vendidos por documento
   • finance_cash_receipts: recibos de caja asociados al cobro del documento
   • sale_payments: pagos recibidos contra el documento de venta
   finance_expense_vouchers aparece sin línea porque registra egresos/gastos internos de forma
   independiente, sin estar atada directamente a un documento de venta.
   Bloque Contabilidad: journal_entry_lines es la tabla de detalle contable y se relaciona con:
   • journal_entries: el asiento contable al que pertenece cada línea (débito/crédito)
   • accounting_accounts: la cuenta del plan contable afectada en cada línea
   Los asientos contables se generan automáticamente desde ventas, compras y pagos mediante
   el campo source_type + source_id (relación polimórfica), por eso journal_entries no muestra línea
   directa hacia sale_documents o purchase_documents en el diagrama.
   Relación:
   • Un documento de venta puede contener muchos ítems de detalle, pero cada ítem de
   detalle pertenece a un solo documento de venta.
   • Un documento de venta puede tener registrados muchos pagos parciales o totales, pero
   cada pago está asociado a un solo documento de venta.
   • Un documento de venta puede generar muchos recibos de caja, pero cada recibo de caja
   está asociado a un solo documento de venta.
   • Un asiento contable puede tener muchas líneas de débito y crédito, pero cada línea
   pertenece a un solo asiento contable.
   • Una cuenta del plan contable puede aparecer en muchas líneas de asientos, pero cada
   línea referencia a una sola cuenta contable.
   • Un cliente (contacto) puede tener muchos documentos de venta asociados, pero cada
   documento de venta pertenece a un solo cliente. (relación visible desde módulo de
   catálogos)
   • Un ítem del catálogo puede aparecer en muchos documentos de venta, pero cada línea
   de detalle referencia a un solo ítem. (relación visible desde módulo de catálogos)
   • Registra egresos y gastos internos de forma independiente. Sus relaciones hacia
   bank_accounts y contacts son visibles desde los módulos de catálogos.
   • Los asientos contables se generan automáticamente desde ventas, compras y pagos
   mediante los campos source_type + source_id, por eso no muestra línea directa hacia
   sale_documents ni purchase_documents en el diagrama.

3.2 Tablas principales
Tabla Descripción Módulo relacionado
 Users Usuarios del sistema con roles y
permisos de acceso
Usuarios y Permisos
 Contacts Clientes y proveedores del negocio Catálogos
Ítems Catálogo de productos, insumos y
materias primas
Catálogos
Warehouses Bodegas donde se almacena el
inventario
Catálogos
product_sheets Sábana de productos — ficha base
de cada referencia productiva
Fichas Técnicas
technical_sheets Ficha técnica detallada de
construcción de cada referencia
Fichas Técnicas
Operations Catálogo de operaciones de
confección disponibles
Catálogos
Machines Catálogo de máquinas utilizadas en
producción
Catálogos
product_sheet_operations Listado operacional — operaciones
asignadas a cada ficha con tiempos
y factores SAM
Listados Operacionales
warehouse_items Stock actual de cada ítem por
bodega
Inventario
inventory_movements Registro de cada entrada y salida
de inventario
Inventario
fabric_rolls Rollos de tela disponibles en
bodega
Inventario
purchase_documents Órdenes de compra y facturas de
proveedor
Compras
purchase_document_items Detalle de ítems por documento de
compra
Compras
purchase_payments Pagos registrados contra
documentos de compra
Compras
sale_documents Facturas y documentos de venta a
clientes
Ventas y Finanzas
sale_document_items Detalle de ítems por documento de
venta
Ventas y Finanzas
sale_payments Pagos recibidos contra documentos
de venta
Ventas y Finanzas
journal_entries Asientos contables generados por
operaciones del sistema
Ventas y Finanzas
accounting_accounts Plan contable — cuentas
disponibles para registros contables
Ventas y Finanzas

3.3 Relaciones clave entre módulos
Catálogos → Todos los módulos
Las tablas items, warehouses, contacts, machines y operations son el núcleo compartido del
sistema. Todos los módulos dependen de estos catálogos para registrar sus operaciones —
ningún documento de compra, venta o movimiento de inventario puede existir sin referenciar
datos del catálogo.
Fichas Técnicas → Listados Operacionales
Una ficha de producto (product_sheets) es el punto de partida para construir el listado
operacional. Las operaciones, tiempos y factores SAM registrados en product_sheet_operations
siempre están ligados a una ficha técnica específica. Sin ficha no puede existir listado
operacional.
Compras → Inventario
Cuando se registra un documento de compra y se recibe la mercancía, se generan movimientos
de entrada en inventory_movements y se actualiza el stock en warehouse_items. Las solicitudes
de inventario (inventory_requests) pueden originar órdenes de compra cuando el stock es
insuficiente.
Ventas → Inventario
Cuando se registra un documento de venta, se generan movimientos de salida en
inventory_movements y se descuenta el stock correspondiente en warehouse_items. Una venta
no puede completarse si no hay existencias disponibles.
Compras → Contabilidad
Cada documento de compra registrado genera automáticamente un asiento contable en
journal_entries con sus respectivas líneas en journal_entry_lines, afectando las cuentas de
proveedores, inventario y gastos según el plan contable definido en accounting_accounts.
Ventas → Contabilidad
Cada documento de venta genera automáticamente un asiento contable que afecta las cuentas
de clientes, ingresos e inventario. Los pagos registrados en sale_payments y los recibos de caja
en finance_cash_receipts también producen sus propios asientos contables.
Inventario → Contabilidad
Los ajustes de inventario (inventory_adjustments) y traslados entre bodegas
(warehouse_transfers) generan asientos contables que reflejan las variaciones de valor del
inventario en la contabilidad.
4. Módulos Funcionales
4.1 Módulo de Usuarios y Permisos
4.1.1 Propósito de negocio
El módulo de Usuarios y Permisos resuelve la necesidad de controlar quién puede acceder al
sistema y qué acciones puede realizar dentro de él. En una empresa como Olamtex, donde
múltiples personas de diferentes áreas (producción, compras, ventas, contabilidad) trabajan
sobre la misma plataforma, es fundamental garantizar que cada empleado solo vea y opere la
información correspondiente a su rol.
Este módulo permite crear usuarios, asignarles roles predefinidos (como administrador, operario
de producción, contador, etc.) y definir permisos específicos por funcionalidad. De esta forma se
protege la integridad de los datos, se evitan modificaciones no autorizadas y se mantiene
trazabilidad de quién realizó cada acción dentro del sistema.
4.1.2 Diagrama de casos de uso (UML)
4.1.3 Flujo principal (diagrama de actividades)
4.1.4 Diagrama de estados
La entidad User no maneja un ciclo de estados en el sistema. Su acceso se controla mediante la
asignación y revocación de roles y permisos, no mediante un campo de estado.
4.1.5 Reglas de negocio importantes
• Un usuario debe tener al menos un rol asignado para poder operar en el sistema.
• Solo el Super Admin puede crear, editar y eliminar usuarios y roles.
• No se pueden eliminar roles que tengan usuarios activos asignados.
• Los permisos directos asignados a un usuario tienen prioridad sobre los permisos
heredados del rol.
• Un usuario inactivo no puede iniciar sesión aunque tenga roles y permisos asignados.
• No pueden existir dos usuarios con el mismo correo electrónico en el sistema.
• Un rol debe tener al menos un permiso asignado para ser funcional.
• Los permisos controlan el acceso a nivel de módulo y de acción (crear, editar, eliminar,
ver), por lo que un usuario puede tener acceso de solo lectura a un módulo sin poder
modificar datos.
• Cada acción relevante dentro del sistema queda registrada en audit_logs con el usuario
que la ejecutó, garantizando trazabilidad completa.
4.1.6 Pantallas / endpoints relevantes
Pantalla / Endpoint
Descripción
GET /login
Pantalla de inicio de sesión
POST /login
Procesa las credenciales e inicia la sesión
POST /logout
Cierra la sesión del usuario activo
GET /forgot-password
Pantalla para solicitar recuperación de contraseña
POST /forgot-password
Envía el correo de recuperación de contraseña
GET /reset
password/{token}
Pantalla para establecer nueva contraseña
GET /usuarios
Pantalla principal de gestión de usuarios
GET /users
Obtiene listado de usuarios en formato JSON
POST /usuarios
Crea un nuevo usuario
PUT /usuarios/{usuario}
Actualiza los datos de un usuario
DELETE
/usuarios/{usuario}
GET /roles
Elimina un usuario
Pantalla principal de gestión de roles
GET /roles-data
Obtiene listado de roles en formato JSON
POST /roles
Crea un nuevo rol
PUT /roles/{role}
Actualiza un rol existente
DELETE /roles/{role}
Elimina un rol
GET /auditoria
Pantalla de registro de auditoría del sistema
GET /settings/profile
Pantalla de edición de perfil del usuario
PUT /settings/password
Actualiza la contraseña del usuario autenticado
GET /configuración
Pantalla general de configuración del sistema
4.1.7 Integraciones externas
Las únicas interacciones externas relacionadas con este módulo son:
SMTP Hostinger — utilizado para el envío del correo de recuperación de contraseña (forgot
password). No es una integración de negocio sino una dependencia de infraestructura ya
documentada en la sección 2.3.
4.2 Módulo de Catálogos
4.2.1 Propósito de negocio
El módulo de Catálogos resuelve la necesidad de centralizar y estandarizar los datos
maestros que utiliza toda la empresa. En Olamtex, antes de registrar una compra, una venta,
un movimiento de inventario o una operación de producción, es necesario que los elementos
base (productos, proveedores, clientes, bodegas, máquinas, operaciones) estén
previamente definidos y unificados en un solo lugar.
Sin este módulo, cada área podría registrar el mismo producto o proveedor con nombres
distintos, generando inconsistencias en los reportes y en la trazabilidad del negocio. Los
catálogos garantizan que toda la empresa hable el mismo idioma al momento de registrar
información.
4.2.2 Diagrama de casos de uso (UML)

4.2.3 Flujo principal (diagrama de actividades)

4.2.4 Diagrama de estados
• Activo (teal) → estado inicial; puede desactivarse o eliminarse.
• Inactivo (ámbar) → alcanzado al desactivar el ítem; puede reactivarse de vuelta a Activo
(canal izquierdo).
• Eliminado (rojo, destacado) → estado final sin retorno, alcanzado únicamente vía soft
delete desde Activo, y solo si no hay registros asociados (representado con línea
punteada por ser condicional, canal derecho).
4.2.5 Reglas de negocio importantes
• No se puede registrar un documento de compra o venta si el contacto (proveedor o
cliente) no existe previamente en el catálogo.
• No se puede crear un movimiento de inventario si el ítem y la bodega no están
registrados en el catálogo.
• No se puede crear una operación sin asignarle una sección operacional.
• No se puede crear un listado operacional si la operación o la máquina no existen en el
catálogo.
• No se pueden eliminar ítems que tengan movimientos de inventario, líneas de compra o
venta asociadas — solo se pueden desactivar.
• No se pueden eliminar contactos que estén referenciados en documentos de compra o
venta existentes.
• No se pueden eliminar bodegas que tengan stock registrado o movimientos de
inventario asociados.
• No se pueden eliminar máquinas u operaciones que estén siendo usadas en listados
operacionales activos.
• Cada ítem debe tener definida su unidad de medida antes de poder usarse en compras
o inventario.
• Los impuestos y retenciones deben estar registrados antes de poder asociarlos a ítems
o documentos de compra y venta.
• Una colección solo puede asignarse a fichas de producto — no tiene uso directo en
compras, ventas ni inventario.
• Los catálogos de material (bases y nombres de tela e hilaza) viven exclusivamente en
las tablas relacionales fabric_bases, yarn_bases, fabric_names y yarn_names. La tabla
settings (parámetros y campos adicionales) ya NO guarda ningún dato de material —
las filas que lo hacían (parámetro "Telas", campo adicional "Base tela"/"Base hilaza",
y las filas huérfanas "Bases de tela"/"Bases de hilaza") se archivaron en
settings_archive y se eliminaron por duplicar esas tablas sin que ningún consumidor
las leyera. Si en el futuro algo necesita restaurarlas, están en settings_archive con
su contenido original.
• El parámetro "Colores de tela" (fabric_colors) de settings también se eliminó (mismo
mecanismo de archivo/reversión) por ser código muerto: ningún formulario del sistema
lo consumía. El catálogo real de colores es el modelo Color (tabla colors), usado por
items.color_id y el selector de color del formulario de ítems — ese no se tocó.
• Las 4 tablas de material se mantienen separadas a propósito (fabric_bases/fabric_names
por un lado, yarn_bases/yarn_names por otro) — no se unificaron en una sola tabla con
el tipo como dato: eso habría hecho depender de código, en vez de la base de datos, que
una hilaza nunca entre donde va una tela. Lo que sí se unificó es la gestión: una sola
pantalla en Datos Maestros (antes las bases se editaban desde un diálogo dentro de
Producción y los nombres en una pantalla aparte).
• La pantalla única de catálogo de materiales pagina y permite buscar tanto bases como
nombres (fabric_bases ya supera las 38 filas reales). La relación base→nombre se
muestra haciendo clic en una fila de "Bases": la tabla de "Nombres" se filtra a solo
los de esa base, con un botón "Ver todos" para quitar el filtro — no son tablas
independientes sin relación visible, aunque siguen siendo dos tablas (no una sola
anidada). El selector de base al crear un nombre siempre lista todas las bases
activas, sin importar en qué página de la tabla esté el usuario.
• El factor de una base de tela (afecta el cálculo del SAM) solo lo ve y edita el rol
DISEÑO — permiso "ver factor material". Cualquier otro rol con acceso al catálogo de
materiales ve la base y el nombre, nunca el factor: ni en la pantalla ni en la
respuesta JSON del endpoint (se retira en el controlador antes de responder).
• Crear una base de tela nueva exige indicar su factor — ya no existe ninguna vía
(incluido el atajo desde el select de la Sábana de productos) que la cree con un
factor de 1.0 por defecto en silencio.
• Un subgrupo de categoría se clasifica como Tela o Hilaza mediante la columna
category_sub_groups.material_kind, editable desde el diálogo de edición de subgrupos
(Datos Maestros → Categorías). Antes esta clasificación vivía hardcodeada en
config/master_data.php (ids de subgrupo fijos en un archivo PHP) — un cambio exigía
editar código y desplegar. Ya no existe ese archivo. La clasificación se cachea
(driver de caché de la app, no memoria de proceso) e invalida automáticamente al
guardar un subgrupo.
• El nombre de un ítem de tela o hilaza incluye ahora su material: subgrupo + base +
nombre + extras + color (ej. "TELA ALGODÓN MILE AZUL REY"), sin paréntesis — el
paréntesis solo aparece en el selector del formulario ("MILE (ALGODÓN)"), nunca en el
nombre guardado. Hay un único punto que arma esta regla en el backend
(ItemService::buildName(), usado tanto por el alta manual como por la carga masiva) y
su espejo en el frontend (ItemFormContent.vue, solo como previsualización — el nombre
real siempre lo decide el backend al guardar). El factor de la base no aparece en
ningún nombre ni en el selector salvo para el rol con el permiso "ver factor material".
• Al migrar un ítem del texto libre histórico (extra_fields, ej. "Base tela") a su FK
real (fabric_name_id), el valor original no se borra ni queda duplicado: se mueve a
extra_fields._legacy, una clave reservada que el nombre del ítem y el formulario
ignoran siempre.
• Al crear un ítem manualmente se elige entre referencia Automática o Manual. Manual
es el campo de texto de siempre, con la misma validación de unicidad. Automática
genera consecutivo(5 dígitos) + código del grupo(2) + código del subgrupo(2) +
código del color(3) — necesita que el grupo, el subgrupo y el color del ítem tengan
su código de 2 (grupo/subgrupo) o 3 (color) dígitos asignado; si falta alguno, el
sistema lo rechaza explicando cuál falta en vez de generar algo incompleto. El
código de grupo/subgrupo se asigna desde Datos Maestros → Categorías (editar grupo:
botón junto al filtro "Grupo"; editar subgrupo: mismo diálogo de siempre). El
consecutivo es atómico (misma tabla de secuencias que usan cotizaciones, pedidos y
facturas) — no se repite aunque dos personas guarden a la vez. Editar un ítem ya
creado **nunca** regenera su referencia, sin importar cómo se creó originalmente.
Este toggle es independiente del patrón ref-color-talla que ya usan los productos
terminados generados desde curvas de remisión — ese flujo no cambió.
• Cambiar el factor de una base de tela desde el catálogo de materiales ya no queda en
silencio: el sistema repropaga el nuevo factor a todo lo que depende de él. La UI pide
confirmación explícita antes de guardar, mostrando cuántas fichas y operaciones se
verían afectadas.
   • Fichas técnicas (listados de operaciones): se recalculan siempre, sin excepción —
   son plantilla, no tienen concepto de avance.
   • Operaciones de órdenes de producción ya generadas: se recalculan SOLO si no
   tienen avance reportado (cantidad avanzada en 0/nulo y sin fecha de avance). Una
   operación con avance conserva el factor y el SAM con los que se midió ese trabajo —
   cambiarlo falsearía la eficiencia histórica ya registrada.
   • ⚠ Consecuencia importante para quien lea un informe de SAM: una misma orden de
   producción puede quedar con algunas operaciones al factor viejo (las que ya
   tenían avance cuando se cambió la base) y otras al factor nuevo (las que no lo
   tenían). No es un error de datos — es el resultado esperado de recalcular por
   operación, no por orden completa.
   • Cada operación recalculada queda en el historial de la operación
   (technical_sheet_operation_history) con el valor anterior y el nuevo, quién hizo
   el cambio de factor y en qué base.
• Un campo adicional de tipo "Lista de opciones" puede tomar sus valores de dos
orígenes: una lista escrita a mano (como siempre) o un catálogo del sistema. Con
origen de catálogo, las opciones que ve el usuario se resuelven en vivo contra la
tabla del catálogo cada vez que se abre el formulario del ítem — nunca quedan
guardadas como lista fija en el campo, así que un valor nuevo en el catálogo (ej. un
nombre de tela agregado en Datos Maestros → Catálogo de materiales) aparece de
inmediato sin editar el campo adicional. El registro de qué catálogos están
disponibles vive en config/additional_field_catalogs.php (hoy: nombres de tela,
nombres de hilaza, colores) — agregar uno nuevo ahí es lo único que hace falta para
ofrecerlo en el selector de origen. El valor que finalmente se guarda en
items.extra_fields sigue siendo texto plano en ambos casos: ningún consumidor de ese
campo (exportación/importación de Excel, generación del nombre del ítem, migración
de datos legados) distingue de dónde vino la opción.

Decisiones de diseño descartadas para el catálogo de materiales (para que el próximo
desarrollador no las reintente creyendo que fue un descuido):
• Unificar fabric_bases/fabric_names y yarn_bases/yarn_names en un catálogo maestro
único con el tipo de material como dato (material_types + material_bases +
material_names). Rechazada por tres motivos concretos: (a) hoy product_sheets.
fabric_base_id apunta a fabric_bases y el motor de base de datos garantiza que ahí
no puede entrar una hilaza — con una tabla única esa garantía desaparece y pasa a
depender de una validación en código; (b) obligaría a que factor sea nullable con
una validación condicional ("obligatorio solo si el tipo lo usa"), cuando hoy la
estructura documenta sola la regla: fabric_bases tiene factor, yarn_bases no; (c) el
beneficio ("un tipo nuevo sin programar") es especulativo — en toda la historia del
sistema han existido dos tipos, tela e hilaza. Se pagaba complejidad cierta hoy por
flexibilidad hipotética. Si algún día entra un tercer tipo real de material (hilo,
cierre…), esta decisión se reevalúa entonces con el caso concreto sobre la mesa, no
antes — el costo de cambiar más adelante es acotado porque MaterialClassifier ya
centraliza la pregunta de clasificación.
• Formulario de ítem generado 100% desde metadata (con storage, storage_column,
render_hint, name_position…). Rechazada por deuda técnica: ~8 dimensiones de
configuración que no se documentan en una página, con alto riesgo de que el
siguiente desarrollador no las entienda y vuelva a hardcodear. La regla que queda es
explicable en una frase: un ítem tiene campos propios (referencia, color, tela/
hilaza según su categoría) y campos adicionales configurables, cuyas opciones se
escriben a mano o vienen de un catálogo del sistema (ver arriba). Si el nombre del
ítem necesitara componerse distinto por categoría (que Tela arme su nombre diferente
a Prenda, por ejemplo), ahí sí haría falta esa metadata de orden — hoy la regla es
única para todo el sistema.
• Un ítem puede tener legacy_code: el identificador con el que existía en el sistema
anterior, guardado tal cual para trazabilidad (no es único ni se valida contra nada —
el sistema anterior podía repetirlo o dejarlo vacío). Se ve en el formulario de ítem
como campo de solo lectura (nunca aparece al crear uno nuevo, porque un ítem nuevo no
tiene código anterior) y tiene su propia columna "CÓDIGO SISTEMA ANTERIOR" en la
plantilla y exportación de Excel.
• La carga masiva de ítems empareja cada fila con un ítem existente de dos formas: por
N° ITEM (si el archivo viene de una exportación previa, como siempre) o, si esa
columna no está o viene vacía, por legacy_code. Esto permite reimportar un Excel
proveniente del sistema anterior (que nunca tuvo N° ITEM de este sistema) y que
actualice los ítems ya migrados en vez de duplicarlos.
• Por defecto, reimportar sigue actualizando todos los campos reconocidos de una fila
que hace match (comportamiento de siempre, sin cambios). Opcionalmente, desde el
diálogo de carga masiva, se puede activar un checklist que restringe la corrida a
tocar solo los campos marcados (ej. actualizar solo el stock de bodega, sin arriesgar
sobrescribir nombre/descripción/color con datos desactualizados del archivo). Esta
restricción solo aplica a filas que actualizan un ítem existente — un ítem nuevo
siempre se crea con todos los datos de su fila, sin excepción. La lista de campos
seleccionables es fija (no hay un mapeador de columnas configurable): son los mismos
que ya reconoce el importador, agrupados donde comparten un mismo dato de fondo (ej.
bodega+precio+cantidad+mínimo+máximo van juntos porque son un único registro de stock
por bodega).
4.2.6 Pantallas / endpoints relevantes
Pantalla / Endpoint
Descripción
GET /contactos
Pantalla principal de gestión de contactos (clientes y
proveedores)
GET /contactos/data
Obtiene listado de contactos en formato JSON
POST /contactos
Crea un nuevo contacto
PUT /contactos/{contact} Actualiza un contacto existente
DELETE
/contactos/{contact}
Elimina un contacto
GET /item
Pantalla principal de gestión de ítems
GET /item/data
Obtiene listado de ítems en formato JSON
Crea un nuevo ítem
POST /item
PUT /item/{id}
Actualiza un ítem existente
DELETE /item/{id}
Elimina un ítem
POST /item/{id}/toggle
status
Activa o desactiva un ítem
GET /item/exportar-excel Exporta el catálogo de ítems a Excel
GET /item/descargar
plantilla
Descarga plantilla Excel para carga masiva
GET /parámetros
Pantalla de gestión de parámetros del sistema (máquinas,
secciones, colecciones, etc.)
POST /parámetros
Crea un nuevo parámetro
PUT /parametros/{id}
Actualiza un parámetro existente
DELETE
/parametros/{id}
Elimina un parámetro
GET /categorias/grupos
Obtiene grupos de categorías
GET
/categorias/subgrupos
Pantalla de gestión de subcategorías
4.2.7 Integraciones externas
La única funcionalidad que interactúa con el exterior es:
Exportación / Importación Excel — permite descargar el catálogo de ítems en formato .xlsx y
cargar ítems masivamente desde una plantilla Excel. Esta operación se procesa localmente en
el servidor sin depender de ningún servicio externo.
4.3 Módulo de Fichas Técnicas / Sábana de Productos
4.3.1 Propósito de negocio
El módulo de Fichas Técnicas resuelve la necesidad de documentar y estandarizar la
construcción de cada referencia de producto que fabrica Olamtex. Antes de llevar una prenda a
producción, es necesario tener definido con exactitud qué materiales se usan, qué procesos se
aplican, qué tallas maneja, qué operaciones de confección requiere y cuánto cuesta fabricarla.
Sin este módulo, la información de cada referencia estaría dispersa en documentos físicos o
archivos de Excel, lo que generaría inconsistencias entre lo que diseña el área de diseño, lo que
produce el área de producción y lo que cotiza el área comercial. La ficha técnica es el documento
central que unifica toda esa información en un solo lugar, permitiendo versionar las referencias,
copiar operaciones entre fichas y calcular automáticamente el costo de producción basado en
los tiempos SAM registrados.
4.3.2 Diagrama de casos de uso (UML)

4.3.3 Flujo principal (diagrama de actividades)
4.3.4 Diagrama de estados
ProductSheet tiene dos estados:

1. incomplete — La ficha fue creada pero aún le faltan datos por completar (materiales,
   procesos, operaciones).
2. complete — La ficha tiene toda su información completa y está disponible para
   producción. Es reversible: si se editan datos y quedan incompletos, vuelve a incomplete.
   TechnicalSheet tiene tres estados:
3. draft — La ficha técnica está en borrador. Los costos aún no han sido calculados.
4. in_progress — La ficha está siendo trabajada; los costos están en proceso de cálculo.
5. completed — La ficha técnica está completa, con todos sus costos calculados y
   persistidos. Es un estado final, sin retorno.
   4.3.5 Reglas de negocio importantes
   • No se puede crear una ficha técnica sin definir al menos la referencia de muestra o la
   referencia de producto.
   • Cada ficha técnica tiene un número de versión que se incrementa automáticamente
   cuando se crea una nueva versión de la misma referencia.
   • No se pueden tener dos fichas activas con la misma referencia y el mismo número de
   versión.
   • Una ficha técnica no puede eliminarse si tiene un listado operacional activo o está
   asociada a una orden de producción — solo puede desactivarse.
   • Las piezas de corte deben tener definidas sus medidas por talla antes de poder usarse
   en producción.
   • El costo de producción de la ficha se calcula automáticamente a partir de los tiempos
   SAM registrados en el listado operacional y el parámetro de costo por minuto configurado
   en el sistema.
   • Al crear una nueva versión de una ficha, el sistema puede copiar automáticamente las
   operaciones de la versión anterior para agilizar el proceso.
   • Una ficha técnica solo puede asignarse a una colección a la vez.
   • Los rollos de tela registrados en la ficha deben existir previamente en el catálogo de
   inventario.
   • Los insumos y materias primas registrados en la ficha deben existir previamente en el
   catálogo de ítems.
   • Solo los usuarios con rol Admin o Producción pueden crear, editar o eliminar fichas
   técnicas.
   • El rol Contabilidad puede ver las fichas técnicas y sus costos pero no puede modificarlas.
   4.3.6 Pantallas / endpoints relevantes
   Pantalla / Endpoint
   Descripción
   GET /produccion/sabana-productos
   Pantalla principal de sábana de productos
   GET /produccion/product-sheets
   Obtiene listado de fichas en formato JSON
   POST /produccion/sabana
   productos
   Crea una nueva ficha técnica
   PUT /produccion/sabana
   productos/{sabana}
   Actualiza una ficha técnica
   DELETE /produccion/sabana
   productos/{sabana}
   Elimina una ficha técnica
   POST /produccion/sabana
   productos/{sabana}/toggle-enabled
   Activa o desactiva una ficha
   POST /produccion/sabana
   productos/{sabana}/convert
   Convierte una ficha de muestra a producto
   GET /produccion/ficha-tecnica Pantalla principal de ficha técnica
   GET /produccion/ficha
   tecnica/{id}/básica
   Información básica de la ficha
   GET /produccion/ficha
   tecnica/{id}/procesos
   Guarda los procesos de la ficha
   POST /produccion/ficha
   tecnica/{id}/procesos
   Guarda los procesos de la ficha
   GET /produccion/ficha
   tecnica/{id}/materia-prima
   Materias primas de la ficha
   PUT /produccion/ficha
   tecnica/{id}/materia-prima
   Guarda las materias primas
   GET /produccion/ficha
   tecnica/{id}/insumos
   Insumos de la ficha
   POST /produccion/ficha
   tecnica/{id}/insumos
   Guarda los insumos de la ficha
   GET /produccion/ficha
   tecnica/{id}/rollos
   Rollos de tela de la ficha
   PUT /produccion/ficha
   tecnica/{id}/rollos
   Guarda los rollos de la ficha
   GET /produccion/ficha
   tecnica/{id}/hilos
   Hilos de la ficha
   PUT /produccion/ficha
   tecnica/{id}/hilos
   Guarda los hilos de la ficha
   GET /produccion/ficha
   tecnica/{id}/costos
   Resumen de costos de la ficha
   GET /produccion/ficha
   tecnica/{id}/imprimir
   Genera vista de impresión de la ficha
   GET /produccion/colecciones Pantalla de gestión de colecciones
   PUT
   /produccion/colecciones/{collection}
   Actualiza una colección

4.3.7 Integraciones externas
El módulo de Fichas Técnicas no tiene integraciones con servicios externos. Opera de forma
completamente interna dentro del sistema Olamtex.
Las únicas funcionalidades que interactúan fuera del flujo normal son:
• Generación de PDF para impresión — el endpoint GET /produccion/ficha
tecnica/{id}/imprimir genera una vista de impresión de la ficha técnica en formato PDF
procesado localmente en el servidor, sin depender de ningún servicio externo.
• Cálculo automático de costos — el sistema calcula el costo de producción de la ficha
consumiendo los parámetros configurados internamente (operation_cost, yarn_cost)
desde la tabla settings, sin llamadas a servicios externos.
4.4 Módulo de Listados Operacionales
4.4.1 Propósito de negocio
El módulo de Listados Operacionales resuelve la necesidad de definir y documentar con exactitud
cuánto tiempo toma fabricar cada prenda y qué operaciones de confección se requieren. En
Olamtex, el tiempo estándar de fabricación (SAM — Standard Allowed Minutes) es el indicador
clave para calcular costos de producción, definir precios de venta, planificar capacidad de planta
y medir eficiencia operativa.
Sin este módulo, los tiempos de producción estarían dispersos en hojas de cálculo o documentos
físicos, lo que impediría tener un costo real y actualizado de cada referencia. El listado
operacional permite asignar a cada ficha de producto sus operaciones de confección con sus
respectivos tiempos óptimos, factores de eficiencia del operario, suplementos de máquina y base
de tela, calculando automáticamente el SAM total unitario y por operación, así como el tiempo
productivo y el costo final del producto.
4.4.2 Diagrama de casos de uso (UML)
4.4.3 Flujo principal (diagrama de actividades)
• Carril Usuario (morado) — selección de la ficha, búsqueda y registro de operaciones,
reordenamiento de la secuencia de producción.
• Primera decisión — ¿la sección es Preparación? Si es sí, se asigna el grupo de
preparación (coral, condicional); si no, continúa directo.
• Carril Sistema (teal) — los cálculos automáticos de SAM unitario, SAM por operación,
acumulación del SAM total de la ficha, y posteriormente el cálculo del costo total.
• Segunda decisión — ¿hay cambios en los factores? Si es sí, el sistema registra el historial
con valores anteriores y nuevos (coral); si no, sigue directo al cálculo de costo.
• Cierre — el listado queda disponible para Contabilidad y el área Comercial.
4.4.4 Diagrama de estados
Los estados de la entidad ProductOperationList son tres:

1. Activa (is_active = true) — La operación está vigente en el listado y contribuye al SAM
   total de la ficha. Es el estado operativo normal.
2. Inactiva (is_active = false) — La operación fue desactivada temporalmente y deja de
   contribuir al SAM total de la ficha. Puede reactivarse para volver a Activa.
3. Eliminada (soft delete) — La operación fue removida del listado. Su registro
   permanece en la base de datos con el campo deleted_at registrado. Es un estado
   final, sin retorno.
   4.4.5 Reglas de negocio importantes
   • No se puede crear un listado operacional sin que exista una ficha técnica asociada.
   • No se puede agregar una operación al listado si la operación o la máquina no existen en
   el catálogo.
   • No se puede repetir la misma operación dentro del listado de una misma ficha — cada
   operación debe ser única por ficha.
   • El campo grupo_preparacion es obligatorio cuando la sección operacional es de tipo
   PREPARACION y debe ser nulo para cualquier otro tipo de sección.
   • El factor de eficiencia del operario debe estar entre 0.5 y 2.0 — no se aceptan valores
   fuera de ese rango.
   • El suplemento de máquina debe ser mayor o igual a 1.0 — no puede ser menor.
   • El tiempo óptimo debe ser mayor o igual a 0 — no se aceptan tiempos negativos.
   • El SAM total unitario y el SAM total por operación se calculan automáticamente — el
   usuario no puede ingresarlos manualmente.
   • Cada vez que se modifican los factores o el SAM de una operación, el sistema registra
   automáticamente el cambio en el historial con el usuario y la fecha.
   • Al usar replace-by-sheet, el sistema hace un hard delete de todas las operaciones
   existentes de la ficha antes de reinsertar las nuevas, para evitar conflictos con
   restricciones de unicidad.
   • No se pueden agregar operaciones en lote (bulk) si hay operaciones duplicadas dentro
   del mismo payload enviado.
   • Si una ficha tiene órdenes de producción asociadas, su listado operacional no debe
   editarse para evitar inconsistencias con órdenes ya en proceso.
   • El costo total del producto se calcula multiplicando el SAM total de la ficha por el
   parámetro operation_cost configurado en el sistema.
   4.4.6 Pantallas / endpoints relevantes
   Pantalla / Endpoint
   Descripción
   GET /produccion/listados-operacionales
   Pantalla principal de listados
   operacionales
   GET /produccion/listados-operacionales/data
   Obtiene operaciones de una ficha
   en formato JSON
   GET /produccion/listados-operacionales/summaries
   Obtiene resumen SAM total por
   ficha
   GET /produccion/listados-operacionales/options Obtiene opciones de secciones,
   operaciones y máquinas
   POST /produccion/listados-operacionales Crea una operación en el listado
   POST /produccion/listados-operacionales/bulk Crea múltiples operaciones en lote
   PUT /produccion/listados-operacionales/{listItem} Actualiza una operación del listado
   DELETE /produccion/listados-operacionales/{listItem} Elimina una operación del listado
   POST /produccion/listados-operacionales/bulk-delete Elimina múltiples operaciones en
   lote
   POST /produccion/listados-operacionales/replace-by
   sheet
   Reemplaza todas las operaciones
   de una ficha
   POST /produccion/listados-operacionales/clear-by
   sheet
   Elimina todas las operaciones de
   una ficha
   GET /produccion/listados
   operacionales/{listItem}/history
   Obtiene historial de cambios de una
   operación
   GET /produccion/base-operaciones Pantalla de gestión de la base de
   operaciones
   GET /produccion/operaciones/data Obtiene listado de operaciones del
   catálogo
   POST /produccion/operaciones Crea una nueva operación en el
   catálogo
   PUT /produccion/operaciones/{operation} Actualiza una operación del
   catálogo
   DELETE /produccion/operaciones/{operation} Elimina una operación del catálogo
   GET /produccion/maquinas/data Obtiene listado de máquinas
   POST /produccion/maquinas Crea una nueva máquina
   GET /produccion/secciones/data Obtiene listado de secciones
   operacionales
   POST /produccion/secciones Crea una nueva sección operacional

4.4.7 Integraciones externas
El módulo de Listados Operacionales no tiene integraciones con servicios externos. Opera de
forma completamente interna dentro del sistema Olamtex.
Su única dependencia externa es indirecta:
• Módulo de Fichas Técnicas — el listado operacional no puede existir sin una ficha técnica
asociada. Los datos de costo calculados en este módulo alimentan directamente el
campo costo de la tabla product_sheets.
• Parámetros del sistema — el cálculo del costo final del producto consume los
parámetros operation_cost y yarn_cost configurados internamente en la tabla settings,
sin llamadas a servicios externos.
4.5 Módulo de Inventario
4.5.1 Propósito de negocio
El módulo de Inventario resuelve la necesidad de controlar en tiempo real las existencias de
materiales, insumos y telas que maneja Olamtex en sus bodegas. En una empresa de
manufactura textil, el inventario es uno de los activos más críticos — sin control preciso del stock,
la empresa puede incurrir en paros de producción por falta de materiales, pérdidas por
vencimiento o deterioro, o sobrecostos por compras innecesarias.
Este módulo permite registrar entradas y salidas de inventario, gestionar traslados entre
bodegas, atender solicitudes de insumos de las áreas de producción, registrar ajustes de
inventario cuando hay diferencias físicas, controlar los rollos de tela disponibles en planta y
bloquear períodos contables para evitar modificaciones retroactivas. Toda entrada o salida queda
registrada en inventory_movements, garantizando trazabilidad completa del movimiento de
materiales desde su recepción hasta su consumo en producción.
4.5.2 Diagrama de casos de uso (UML)
4.5.3 Flujo principal (diagrama de actividades)
La orden de compra aprobada pasa a Logística para verificación física, y luego el Sistema registra
automáticamente la entrada y actualiza el stock. Si hay diferencias entre lo ordenado y lo recibido,
se crea un ajuste antes de cerrar el flujo.
SALIDA POR SOLICITUD DE INSUMOSProducción crea la solicitud, Logística verifica disponibilidad (primera decisión), despacha los
materiales y el Sistema registra automáticamente la salida descontando el stock. Si se necesita
mover materiales entre bodegas (segunda decisión), Logística crea el traslado y el Sistema
genera simultáneamente la salida en origen y la entrada en destino.
AJUSTE DE INVENTARIOLogística realiza el conteo físico, y si hay diferencias con el sistema crea un ajuste indicando el
motivo; el Sistema registra automáticamente las líneas de ajuste, actualiza el stock y deja el
registro disponible para auditoría contable. Si no hay diferencias, el flujo termina directamente.
4.5.4 Diagrama de estados
Los estados de la entidad InventoryRequest son tres:

1. solicitado (STATUS_REQUESTED) — La solicitud fue registrada por el área de
   Producción y está en espera de atención por Logística.
2. entregado (STATUS_DELIVERED) — Logística despachó los materiales solicitados. El
   sistema registra automáticamente la salida en inventory_movements y descuenta el stock
   en warehouse_items. Es un estado final, sin retorno.
3. rechazado (STATUS_REJECTED) — La solicitud fue rechazada por Logística,
   registrando el motivo del rechazo y el usuario que lo realizó. También es un estado final,
   sin retorno.
   4.5.5 Reglas de negocio importantes
   • Los movimientos de inventario son de dos tipos únicos: entrada o salida — no existen
   otros tipos en el sistema.
   • Todo movimiento registra automáticamente el stock anterior (previous_stock), el stock
   nuevo (new_stock), el precio promedio ponderado anterior y el nuevo, garantizando
   trazabilidad completa.
   • Cada movimiento está vinculado a un origen mediante relación polimórfica
   (source_type + source_id) — puede originarse desde una compra, un ajuste, una
   solicitud de insumos u otro documento.
   • Los ajustes de inventario también son de tipo entrada o salida — no existe un tipo
   "ajuste" independiente.
   • Un traslado entre bodegas requiere definir obligatoriamente una bodega de origen y
   una bodega de destino — no pueden ser la misma.
   • Una solicitud de insumos solo puede tener tres estados: solicitado, entregado o
   rechazado — no existe estado intermedio.
   • No se puede eliminar un traslado o ajuste que ya generó movimientos de inventario
   asociados — solo puede eliminarse mediante soft delete.
   • El stock en warehouse_items se actualiza automáticamente con cada movimiento —
   el usuario no puede modificarlo manualmente.
   • Los períodos contables bloqueados en inventory_period_locks impiden registrar
   movimientos con fecha dentro del período bloqueado.
   • Una solicitud rechazada debe registrar obligatoriamente el motivo del rechazo
   (rejection_reason) y el usuario que rechazó (rejected_by_user_id).

   Reglas específicas de rollos de tela (fabric_rolls):
   • El número de rollo se reserva de forma atómica en el momento de guardar, desde la
   tabla roll_number_sequences. El número que muestra el formulario antes de guardar es
   solo un preview: si otro usuario se adelanta y lo ocupa, el sistema asigna el siguiente
   de la secuencia en vez de fallar. Un número escrito manualmente se respeta tal cual.
   • Los importadores nunca descartan filas en silencio. Todo rollo duplicado —dentro del
   archivo o ya existente en el sistema— se reporta como error por fila, indicando el
   número de rollo. Antes solo se sumaba a un contador de "omitidos" sin explicar la causa,
   mezclado con filas vacías y cantidades inválidas.
   • La importación de cantidades rechaza el archivo completo si un mismo número de rollo
   aparece en más de una fila, nombrando las filas implicadas. Procesarlo dos veces
   generaría dos ajustes y dos movimientos de kardex sobre el mismo rollo, con el segundo
   delta calculado sobre el resultado del primero: saldos contradictorios.
   • Los rollos con sufijo -DUP1 / -DUP2 son históricos: el mismo rollo físico registrado
   dos veces por colisiones en migraciones antiguas. El comando
   inventory:check-fabric-integrity los lista para revisión manual, pero no los consolida
   automáticamente — fusionarlos exige decidir qué pasa con su inventario y sus
   movimientos, y si dos no eran realmente el mismo rollo se perdería trazabilidad.
   • La programación de rollos que hace Diseño en cada ficha técnica (rollo, tela, metros
   programados) vive en la tabla fabric_roll_programming_lines — ya no en un JSON dentro
   de technical_sheet_rolls. La columna JSON (lines) que servía de respaldo ya se eliminó
   (migración de limpieza ejecutada tras verificar en producción que la tabla nueva
   coincidía exactamente: mismo conteo de líneas y misma suma de metros programados que
   el JSON). TechnicalDataSheetRollsStore sigue siendo el único punto de
   lectura/escritura de esa programación — nada más debe leer technical_sheet_rolls.lines
   directamente ni fabric_roll_programming_lines por fuera del store, para no volver a
   quedar con datos desactualizados como pasó con 5 consumidores (PDF de ficha técnica,
   reseteo de programación, diálogo de consumo de rollos en Corte, entre otros) que leían
   el JSON directamente antes de esta normalización.
   • Pantalla nueva "Programación de rollos" (Kardex de Telas → Inventario Textil) cruza
   esa programación con production_roll_consumptions (consumo real registrado por Corte)
   para mostrar, por línea: programado, disponible, consumido real y diferencia — el
   control que antes no existía para que Diseño vea de un vistazo si lo programado
   coincide con lo que Corte realmente usó.
   4.5.6 Pantallas / endpoints relevantes
   Pantalla / Endpoint
   Descripción
   GET /bodegas
   GET /bodegas/data
   Pantalla principal de gestión de bodegas
   POST /bodegas
   Obtiene listado de bodegas en formato JSON
   Crea una nueva bodega
   PUT /bodegas/{warehouse}
   Actualiza una bodega
   DELETE /bodegas/{warehouse}
   Elimina una bodega
   GET /inventario/item
   GET /inventario-item/data
   Pantalla principal de inventario de ítems
   Obtiene stock actual por ítem en formato
   JSON
   Obtiene stock actual por bodega
   GET /warehouse-items
   GET /inventario/ajuste-inventario
   Pantalla de ajustes de inventario
   GET /ajustes-inventario/data
   POST /ajustes-inventario
   Obtiene listado de ajustes en formato JSON
   Crea un nuevo ajuste de inventario
   PUT /ajustes-inventario/{id}
   Actualiza un ajuste de inventario
   DELETE /ajustes-inventario/{id}
   Elimina un ajuste de inventario
   GET /movimientos-inventario
   GET /movimientos-inventario/data
   Pantalla de movimientos de inventario
   Obtiene listado de movimientos en formato
   JSON
   POST /movimientos-inventario
   Registra un movimiento de inventario
   POST /movimientos-inventario/transfer
   Registra un traslado entre bodegas
   DELETE /movimientos-inventario/{id}
   Elimina un movimiento de inventario
   GET /transferencias-bodegas
   PUT /transferencias-bodegas/{transfer}
   Pantalla de transferencias entre bodegas
   Actualiza una transferencia
   DELETE /transferencias-bodegas/{transfer} Elimina una transferencia
   GET /inventario/solicitudes-internas
   GET /inventario/solicitudes-internas/data
   Pantalla de solicitudes internas de insumos
   Obtiene listado de solicitudes en formato
   JSON
   POST /inventario/solicitudes-internas
   Crea una nueva solicitud de insumos
   POST /inventario/solicitudes
   internas/{id}/deliver
   Marca la solicitud como entregada
   POST /inventario/solicitudes
   internas/{id}/reject
   Rechaza una solicitud de insumos
   4.5.7 Integraciones externas
   El módulo de Inventario no tiene integraciones con servicios externos. Opera de forma
   completamente interna dentro del sistema Olamtex.
   Sus únicas dependencias son con otros módulos internos:
   • Módulo de Compras — cuando se aprueba la recepción de una orden de compra, el
   sistema genera automáticamente una entrada en inventory_movements y actualiza el
   stock en warehouse_items.
   • Módulo de Ventas — cuando se emite una factura de venta, el sistema genera
   automáticamente una salida en inventory_movements y descuenta el stock
   correspondiente.
   • Módulo de Contabilidad — los ajustes de inventario y traslados entre bodegas pueden
   generar asientos contables automáticos en journal_entries según la configuración del
   plan contable.
   4.6 Módulo de Compras
   4.6.1 Propósito de negocio
   El módulo de Compras resuelve la necesidad de gestionar y controlar todo el proceso de
   adquisición de materiales, insumos y servicios que requiere Olamtex para su operación. En una
   empresa de manufactura textil, las compras son una de las actividades más críticas — el
   abastecimiento oportuno de telas, hilos e insumos determina directamente la capacidad de
   cumplir con los pedidos de producción.
   Este módulo permite gestionar el flujo completo desde la solicitud de compra hasta el pago al
   proveedor, pasando por la orden de compra, la recepción de mercancía y la factura del
   proveedor. Cada etapa del proceso tiene sus propios estados, aprobaciones y registros,
   garantizando trazabilidad completa de cada peso gastado y cada ítem adquirido. Además, cada
   documento de compra genera automáticamente los movimientos de inventario y asientos
   contables correspondientes.
   4.6.2 Diagrama de casos de uso (UML)
   4.6.3 Flujo principal (diagrama de actividades)

Flujo principal — Compras
Este flujo documenta el ciclo de vida completo de una compra dentro del sistema, desde que
se origina la necesidad hasta que se completa el pago y se generan los movimientos
contables e inventario correspondientes. Se basa en tres entidades de estado:
PurchaseRequestStatus (solicitud), PurchaseOrderStatus (orden) y PurchaseInvoiceStatus
(factura).
Parte 1: Solicitud de compra y orden de compra
Creación y aprobación de la solicitud. Contabilidad o Admin crea la solicitud de compra, que
nace en estado draft (borrador). Cuando está lista, se envía a aprobación y pasa a pending
(pendiente). En este punto el sistema espera una decisión: si Admin o Contabilidad la
aprueba, pasa a approved y el flujo continúa. Si en cambio se rechaza, queda en rejected; si
se cancela, queda en cancelled. Ambos son estados finales sin retorno — la solicitud no
puede revivir desde ahí, habría que crear una nueva.
Creación y aprobación de la orden de compra. Una vez que la solicitud está aprobada, se
crea una orden de compra asociada, que también nace en pending. Esta orden pasa por su
propia aprobación independiente: si Admin o Contabilidad la aprueba, queda en approved;
si se rechaza, queda en rejected (fin del flujo, sin retorno). Es importante notar que la solicitud
y la orden tienen ciclos de aprobación separados — aprobar la solicitud no aprueba
automáticamente la orden.
Envío opcional al proveedor. Con la orden ya aprobada, existe un paso opcional: enviarla al
proveedor por correo en formato PDF. Si se hace, la orden pasa a sent. Si no se hace, el
flujo continúa de todas formas — este paso no es obligatorio para avanzar.
Conversión a factura. El paso que cierra esta primera mitad del flujo es convertir la orden de
compra en una factura de compra. En este momento ocurren dos cosas simultáneas: la orden
pasa a invoiced (facturada) y se crea un nuevo documento, la factura, que nace en estado
pending_receipt (pendiente de recepción).
Parte 2: Recepción, pago y cierre contable
Recepción física de la mercancía. Logística o Contabilidad recibe físicamente la mercancía
y la compara contra lo que se ordenó. Aquí aparece la primera decisión: si hay diferencias
entre lo ordenado y lo recibido (por ejemplo, llegó menos cantidad o un producto distinto), se
debe gestionar esa diferencia, lo cual implica aprobar o rechazar el ajuste correspondiente
antes de continuar.
Decisión de aceptar o rechazar la factura. Resuelta (o inexistente) la diferencia, surge la
segunda decisión: si la factura se rechaza, queda en rejected y el flujo termina ahí, sin
retorno. Si no se rechaza, la factura avanza a received (recibida), confirmando que la
mercancía fue aceptada conforme a lo esperado.
Registro del pago. Con la factura en estado received, Contabilidad registra el pago
correspondiente. Esta acción crea un nuevo registro en la tabla purchase_payments,
formalizando que la obligación ha sido cubierta.
Cierre automático del sistema. Como consecuencia directa del registro de pago, el sistema
genera automáticamente dos cosas sin intervención humana: la entrada de inventario
correspondiente a la mercancía recibida, y el asiento contable que refleja la transacción
financiera. Con esto, el flujo se considera completo.
4.6.4 Diagrama de estados

Son tres entidades distintas, cada una con su propio conjunto de estados:
PurchaseRequest (solicitud de compra) tiene seis estados:

1. draft — Borrador, recién creada y aún no enviada a aprobación.
2. pending — Pendiente, esperando aprobación.
3. approved — Aprobada, lista para generar la orden de compra.
4. processed — Procesada, ya tiene orden de compra generada. Estado final, sin
   retorno.
5. rejected — Rechazada. Estado final, sin retorno.
6. cancelled — Cancelada. Estado final, sin retorno.
   PurchaseOrderStatus (orden de compra) tiene cinco estados:
7. pending — Pendiente, recién creada y aún editable.
8. approved — Aprobada, lista para convertirse en factura.
9. sent — Enviada al proveedor por correo en formato PDF (paso opcional).
10. invoiced — Facturada, ya tiene factura de compra generada. Estado final, sin retorno.
11. rejected — Rechazada. Estado final, sin retorno.
    PurchaseInvoiceStatus (factura de compra) tiene tres estados:
12. pending_receipt — Pendiente de recepción, aún editable.
13. received — Recibida; la mercancía fue verificada físicamente y genera la entrada en
    inventario. Estado final, sin retorno.
14. rejected — Rechazada. Estado final, sin retorno.
    4.6.5 Reglas de negocio importantes
    • Una solicitud de compra solo puede enviarse a aprobación desde estado draft — no
    desde ningún otro estado.
    • Una solicitud solo puede aprobarse o rechazarse desde estado pending — no desde
    draft, approved ni cancelled.
    • Una solicitud solo puede cancelarse desde estados draft o pending — una vez
    aprobada o rechazada no puede cancelarse.
    • Una solicitud rechazada debe registrar obligatoriamente el motivo del rechazo
    (rejection_reason), el usuario que rechazó y la fecha.
    • Una solicitud solo puede marcarse como processed si está en estado approved — no
    puede saltarse el paso de aprobación.
    • Una orden de compra solo puede editarse cuando está en estado pending — una vez
    aprobada no puede modificarse.
    • Una orden de compra solo puede convertirse en factura cuando está en estado
    approved — no desde pending ni sent.
    • Una factura de compra solo puede editarse cuando está en estado pending_receipt
    — una vez recibida o rechazada no puede modificarse.
    • Una factura de compra solo puede rechazarse desde estado pending_receipt.
    • Cada línea de detalle de un documento de compra (PurchaseDocumentItem) registra
    cantidad, precio, impuesto, total e impuesto total por separado.
    • Un ítem en una línea de compra puede referenciarse por su item_id del catálogo o
    por su nombre de proveedor (item_name) si no existe en el catálogo.
    • Solo usuarios con permiso aprobar facturas compra pueden aprobar facturas de
    compra.
    • Solo usuarios con permiso aprobar orden compra pueden aprobar órdenes de
    compra.
    4.6.6 Pantallas / endpoints relevantes
    Pantalla / Endpoint
    Descripción
    GET /gastos/solicitudes-compra
    Pantalla principal de solicitudes de
    compra
    GET /gastos/solicitudes-compra/data
    Obtiene listado de solicitudes en formato
    JSON
    POST /gastos/solicitudes-compra
    GET /gastos/solicitudes-compra/{id}
    Crea una nueva solicitud de compra
    Ver detalle de una solicitud
    PUT /gastos/solicitudes-compra/{id}
    DELETE /gastos/solicitudes-compra/{id}
    Actualiza una solicitud de compra
    Elimina una solicitud de compra
    POST /gastos/solicitudes-compra/{id}/send-to
    approval
    Envía la solicitud a aprobación
    POST /gastos/solicitudes-compra/{id}/approve
    POST /gastos/solicitudes-compra/{id}/reject
    Aprueba una solicitud de compra
    POST /gastos/solicitudes-compra/{id}/cancel
    Rechaza una solicitud de compra
    GET /gastos/ordenes-compra
    Cancela una solicitud de compra
    GET /gastos/ordenes-compra/data
    Pantalla principal de órdenes de compra
    Obtiene listado de órdenes en formato
    JSON
    POST /gastos/ordenes-compra
    GET /gastos/ordenes-compra/{id}
    Crea una nueva orden de compra
    DELETE /gastos/ordenes-compra/{id}
    Ver detalle de una orden de compra
    PATCH /gastos/ordenes-compra/{id}/approve
    Elimina una orden de compra
    Aprueba una orden de compra
    POST /gastos/ordenes-compra/{id}/convert-to
    invoice
    Convierte la orden en factura de compra
    POST /gastos/ordenes-compra/{id}/enviar
    correo-pdf
    Envía la orden al proveedor por correo
    PDF
    GET /gastos/facturas-compra
    GET /gastos/facturas-compra/data
    Pantalla principal de facturas de compra
    Obtiene listado de facturas en formato JSON
    4.6.7 Integraciones externas
    • SMTP Hostinger — el endpoint POST /gastos/ordenes-compra/{id}/enviar-correo
    pdf envía la orden de compra en formato PDF al proveedor por correo electrónico
    usando el servicio SMTP configurado en el .env. Es la única integración externa real
    de este módulo.
    Las demás interacciones son internas entre módulos:
    • Módulo de Inventario — cuando una factura de compra pasa a estado received, el
    sistema genera automáticamente una entrada en inventory_movements y actualiza el
    stock en warehouse_items.
    • Módulo de Contabilidad — cada documento de compra aprobado genera
    automáticamente asientos contables en journal_entries afectando las cuentas de
    proveedores, inventario y gastos.
    4.7 Módulo de Ventas
    4.7.1 Propósito de negocio
    El módulo de Ventas resuelve la necesidad de gestionar el ciclo completo de ventas de
    Olamtex, desde la cotización inicial al cliente hasta el cobro de la factura. Permite
    documentar y controlar cada etapa del proceso comercial: cotización, pedido de venta,
    factura de venta, notas crédito/débito y recibos de caja.
    Sin este módulo, el área comercial no tendría trazabilidad de cuánto se cotizó, cuánto se
    facturó y cuánto se cobró por cliente, lo que impediría tomar decisiones informadas sobre
    cartera, rentabilidad y cumplimiento de pedidos.
    4.7.2 Diagrama de casos de uso (UML)

4.7.3 Flujo principal (diagrama de actividades)
El flujo de ventas comienza cuando Contabilidad o Admin crea una cotización en estado draft, la
cual se envía al cliente pasando a sent; según la respuesta del cliente, la cotización puede
aceptarse (accepted), rechazarse (rejected, fin del flujo) o vencer (expired, fin del flujo). Si se
acepta, se convierte en pedido de venta —la cotización pasa a converted y nace un pedido en
draft— que luego se confirma (confirmed) y entra a producción (in_process). Una vez producido,
el pedido se convierte en factura de venta —el pedido pasa a invoiced y nace una factura en
draft—, la cual se emite quedando en issued; a partir de ahí pueden registrarse uno o varios
pagos parciales (partial) hasta completar el pago total y llegar a paid, que es el cierre exitoso del
proceso. En paralelo, desde que la factura está emitida pueden ocurrir dos situaciones
especiales: si hay devoluciones se crea una nota de crédito o débito para ajustar el saldo, o si es
necesario anular la operación, la factura pasa directamente a voided, cerrando el flujo de forma
negativa.
4.7.4 Diagrama de estados
Este primer diagrama es QuoteStatus: draft → sent, con tres rutas posibles desde ahí: accepted
(centro, que continúa hacia converted), rejected (izquierda, final) o expired (derecha, final).
Este segundo diagrama es SalesOrderStatus: draft → confirmed → in_process → invoiced (flujo
central), con dos puntos de salida hacia cancelled —desde draft (canal externo) y desde
confirmed (canal interno)— ambos por la izquierda para no cruzarse.
Este tercer diagrama es SalesInvoiceStatus: draft → issued, desde donde hay tres rutas: pago
directo a paid (canal derecho), pago parcial a partial (centro, que luego también puede
completarse hacia paid), o anulación a voided (canal izquierdo, accesible tanto desde issued
como desde partial).
4.7.5 Reglas de negocio importantes
• Una cotización solo puede convertirse en pedido de venta desde estado sent o accepted.
• Una cotización solo puede editarse si no está en estado accepted ni converted.
• Una cotización solo puede eliminarse desde estado draft.
• Un pedido de venta solo puede editarse desde estados draft o confirmed.
• Un pedido solo puede eliminarse desde estado draft.
• Un pedido solo puede convertirse en factura desde estados confirmed o in_process.
• Una factura solo puede emitirse desde estado draft.
• Una factura solo puede anularse desde estados issued o partial.
• Una factura solo puede editarse desde estado draft.
• El total neto de la factura se calcula sumando por cada línea: (cantidad × precio) -
descuento + impuesto.
4.7.6 Pantallas / endpoints relevantes
Pantalla / Endpoint
Descripción
GET /income/quotes
Pantalla principal de cotizaciones
GET /income/quotes/data
Obtiene listado de cotizaciones en formato JSON
POST /income/quotes
Crea una nueva cotización
PUT /income/quotes/{id}
Actualiza una cotización
DELETE
/income/quotes/{id}
Elimina una cotización
POST
/income/quotes/{id}/convert
Convierte cotización en pedido de venta
GET /income/sales-orders
Pantalla principal de pedidos de venta
POST /income/sales
orders
Crea un nuevo pedido de venta
PUT /income/sales
orders/{id}
Crea un nuevo pedido de venta
DELETE /income/sales
orders/{id}
Elimina un pedido de venta
POST /income/sales
orders/{id}/convert
Convierte pedido en factura de venta
GET /income/sales
invoices
Pantalla principal de facturas de venta
GET /income/sales
invoices/data
Obtiene listado de facturas en formato JSON
POST /income/sales
invoices
Crea una nueva factura de venta
PUT /income/sales
invoices/{id}
Actualiza una factura de venta
DELETE /income/sales
invoices/{id}
Elimina una factura de venta
POST /income/sales
invoices/{id}/emit
Emite una factura de venta
POST /income/sales
invoices/{id}/void
Anula una factura de venta
GET /income/credit-debit
notes
Pantalla de notas crédito/débito
POST /income/credit-debit
notes
Crea una nota crédito/débito
POST /income/credit-debit
notes/{id}/emit
Emite una nota crédito/débito
POST /income/credit-debit
notes/{id}/void
Anula una nota crédito/débito
GET /income/cash-receipts Pantalla de recibos de caja

4.7.7 Integraciones externas
El módulo de Ventas no tiene integraciones con servicios externos directos. Sus
dependencias son internas:
• Módulo de Inventario — al emitir una factura de venta el sistema genera
automáticamente una salida en inventory_movements y descuenta el stock
en warehouse_items.
• Módulo de Contabilidad — cada factura emitida genera automáticamente asientos
contables en journal_entries afectando las cuentas de clientes, ingresos e inventario.
• SMTP Hostinger — si el sistema tiene configurado envío de facturas por correo, usa
el servicio SMTP del .env. Esta funcionalidad depende de la configuración
del MAIL_MAILER.

5. Documentación Técnica de Código
   5.1 Convenciones de código
   Nomenclatura – Backend (PHP / Laravel)
   Elemento  Convención Ejemplo real del proyecto
   Clases /
   Modelos
   PascalCase  PurchaseDocument, TechnicalSheet
   Controladores  PascalCase +
   sufijo Controller
   PurchaseOrderController
   Servicios  PascalCase +
   sufijo Service
   ProductOperationListService
   Enums PascalCase PurchaseOrderStatus, QuoteStatus
   Métodos camelCase sendToApproval(), markAsProcessed()
   Columnas de
   BD
   snake_case approved_by_user_id, rejected_at
   Tablas de BD snake_case plural purchase_documents, product_sheet_operations
   Rutas (URL) kebab-case /solicitudes-compra, /ordenes-compra
   Nombres de
   rutas
   dot.notation gastos.purchase-orders.approve
   Variables camelCase $purchaseRequest, $userId
   Constantes de
   clase
   UPPER_SNAKE_CASE TYPE_REQUEST, STATUS_DRAFT

Nomenclatura – Frontend (Vue.js / TypeScript)
Elemento  Convención Ejemplo real del proyecto
 Componentes Vue  PascalCase
OperationCatalogManagerDialog.vue
Archivos de páginas  PascalCase  Index.vue dentro de carpeta por
módulo
Variables / funciones  camelCase isLoading, handleSubmit
Props camelCase en script, kebab
case en template
:purchase-id
Clases CSS kebab-case (Tailwind) text-slate-700, bg-emerald-100

Estructura de archivos
app/
├── Enums/
│   └── Income/
← Enums de estados por módulo
← Sub-namespace por módulo
├── Http/Controllers/   ← Controladores agrupados por módulo
│   ├── Expenses/
│   ├── Income/
│   ├── Production/
│   └── Settings/
├── Models/
│   ├── Expenses/
│   ├── Income/
│   ├── Inventory/
│   └── Production/
├── Services/
└── Contracts/
resources/js/
└── pages/
├── Income/
├── Production/
└── Operations/
routes/
← Modelos Eloquent agrupados por módulo
← Lógica de negocio separada del controlador
← Interfaces (ej: HasAuditLabel)
← Páginas Inertia agrupadas por módulo
← Un archivo de rutas por módulo
├── auth.php
├── expenses.php
├── income.php
├── inventory.php
├── master-data.php
├── production.php
└── settings.php
Linters y formatters (configuración real del proyecto)
Herramienta
Uso
Comando
ESLint 9
Linting de
JavaScript/TypeScript/Vue
npm run lint
Prettier 3
Formateo de código frontend
Laravel Pint
npm run format
Formateo de código PHP (PSR
12)
Reglas Prettier activas (.prettierrc):
• Comillas simples (singleQuote: true)
• Punto y coma obligatorio (semi: true)
• Ancho de línea máximo: 150 caracteres
• Indentación: 4 espacios (2 para archivos .yml)
./vendor/bin/pint
• Organización automática de imports (prettier-plugin-organize-imports)
• Ordenamiento de clases Tailwind (prettier-plugin-tailwindcss)
Reglas ESLint activas (eslint.config.js):
• Basado en eslint-plugin-vue + @vue/eslint-config-typescript
• vue/multi-word-component-names: desactivado (permite nombres de un solo término)
• @typescript-eslint/no-explicit-any: desactivado
• Excluye: vendor/, node_modules/, public/, resources/js/components/ui/
5.2 Patrones de diseño identificados
MVC — Model-View-Controller
Dónde: en toda la aplicación, base del framework Laravel.
• Model: app/Models/ — Eloquent ORM, lógica de datos y relaciones.
• View: resources/js/pages/ — componentes Vue.js renderizados con Inertia.js (sin Blade).
• Controller: app/Http/Controllers/ — recibe la solicitud HTTP, delega al Service, retorna
respuesta Inertia.
Service Layer
Dónde: app/Services/ — 60+ servicios organizados por módulo.
La lógica de negocio no vive en el controlador sino en clases Service dedicadas. El controlador
solo valida la solicitud y llama al servicio.
Servicio
Responsabilidad
PurchaseRequestService
Flujo de solicitudes de compra
ProductOperationListService
Cálculos SAM y operaciones en lote
AccountingPostingService
Generación de asientos contables
SalesInvoiceService
Ciclo de facturas de venta
Observer
Dónde:
app/Observers/AuditObserver.php
app/Observers/PurchaseDocumentInventoryObserver.php
y
Los observers escuchan eventos del ciclo de vida de los modelos Eloquent (created, updated,
deleted) sin que los modelos sepan quién los observa. El AuditObserver registra
automáticamente toda modificación en el log de auditoría.
Sequence / Number Generator (patrón custom)
Dónde: app/Services/Finance/*NumberSequenceService.php — hay un servicio por cada tipo de
documento.
Cada tipo de documento (factura de venta, factura de compra, nota crédito, etc.) tiene su propio
servicio que reserva el siguiente número de manera atómica usando DB::transaction con
lockForUpdate(), evitando números duplicados bajo concurrencia.
Contract / Interface
Dónde: app/Contracts/HasAuditLabel.php
Define el contrato HasAuditLabel que obliga a cada modelo auditable a implementar el método
auditLabel(): string. Esto permite al AuditObserver obtener el nombre legible del modelo sin
acoplarse a cada clase concreta.
Enum como State Machine
Dónde: app/Enums/ — todos los enums de estado del proyecto.
Los enums no solo definen los estados posibles sino que encapsulan las reglas de transición.
Cada enum tiene métodos como canEdit(), canApprove(), canReject(), isFinal() que definen
desde qué estado se puede hacer cada acción, evitando que esa lógica se repita en
controladores o servicios.
Soft Delete
Dónde: en la mayoría de modelos del proyecto (SoftDeletes trait de Laravel).
Los registros nunca se eliminan físicamente de la base de datos. Al "eliminar" un registro se llena
la columna deleted_at. Esto permite recuperar datos y mantener trazabilidad histórica.
Polymorphic Relationship
Dónde: inventory_movements, audit_logs, tablas de permisos de Spatie.
Permite que una tabla se relacione con múltiples modelos usando los campos model_type y
model_id, evitando crear tablas intermedias por cada combinación.
5.3 Diagramas de secuencia
Diagrama 1 — Recepción de factura de compra Este es el flujo más complejo del sistema: un
solo clic en "Recibir factura" dispara en cadena validación, cambio de estado, movimientos de
inventario y asiento contable, todo dentro de una transacción atómica.
El
Usuario
dispara PATCH /recibir, el Controller invoca receiveInvoice() en
PurchaseDocumentInvoiceService, que abre la transacción, bloquea y valida la factura, actualiza
sus estados, delega en InventoryMovementService para crear los movimientos de inventario y
en AccountingPostingService para generar el asiento contable, confirma la transacción con
commit, y la respuesta regresa en cascada hasta el 200 OK final.

Diagrama 2 — Creación de factura de compra desde orden
5.4 Integraciones con sistemas externos
Sistema externo
Propósito
SMTP (Hostinger)
Envío de orden de compra en
PDF al proveedor por correo
electrónico
Módulo que lo usa
Compras — POST
/gastos/ordenes
compra/{id}/enviar-correo
pdf
MySQL (Hostinger)
Base de datos principal de toda la
aplicación
Storage local (disco del
servidor)
Almacenamiento de archivos
adjuntos: imágenes de ítems,
avatares de usuarios,
documentos PDF generados,
evidencias de rechazo
Todos los módulos
Producción, Configuración,
Compras, Datos Maestros
Lo que NO existe en el código:
• No hay pasarela de pago (Stripe, PayPal, etc.)
• No hay facturación electrónica (DIAN, SAT, SRI u otro)
• No hay integración con AWS S3 (las variables están
en .env.example pero FILESYSTEM_DISK=local)
• No hay llamadas a APIs externas (Http::get, Http::post, Guzzle)
• No
hay
Redis
activo
en
(QUEUE_CONNECTION=database, CACHE_STORE=database)
• No hay Memcached activo
• No hay servicios de notificaciones push externas
5.5 Deuda técnica conocida
producción
• Módulo de Contabilidad incompleto — el módulo de finanzas/contabilidad existe en el
código (app/Services/Finance/) pero no está terminado ni documentado. Hay servicios
como
AccountingPostingService,
JournalEntryNumberSequenceService
y
TreasuryVoucherService que se invocan desde otros módulos pero cuyas pantallas y
flujos de usuario aún no están completos.
• Cobertura de pruebas parcial — existen pruebas en tests/Feature/ y tests/Unit/ pero solo
cubren módulos específicos (autenticación, roles, producción, ventas). Módulos como
Inventario, Compras y Contabilidad no tienen pruebas automatizadas. Las pruebas
Browser (tests/Browser/) cubren parcialmente el módulo de Compras.
• Rutas legacy pendientes de eliminar — en routes/expenses.php hay dos secciones
marcadas explícitamente como legacy:
o CUENTAS POR PAGAR (LEGACY DESCONTINUADO) — redirige
a /finanzas/comprobantes-egreso pero aún no se ha eliminado
o PAGOS DE FACTURAS (LEGACY TRANSITORIO) — endpoints de pagos con
estructura antigua que deben migrarse
• Rutas de avatar duplicadas — en routes/settings.php existen rutas de avatar marcadas
como legacy aliases to avoid breaking existing integrations que deben eliminarse una vez
confirmada la migración a los nuevos endpoints.
• TODO
pendiente
en
producción
—
en
app/Services/Production/TechnicalDataSheetSuppliesStore.php hay un comentario //
TODO: integrar con PurchaseDocument / PurchaseOrderDetail, indicando que los
insumos de la ficha técnica aún no están integrados con el flujo real de compras.
• Migraciones pendientes en producción — existen dos migraciones sin ejecutar en el
servidor de producción (2026_06_22_000001 y 2026_06_22_000002) que agregan
columnas requeridas en product_sheet_operations. Estas migraciones causan errores
422/500 en el endpoint /listados-operacionales/bulk hasta que se ejecuten.
• Módulo de módulo Textil aislado — existe app/Services/Textil/FabricRollService.php y
comandos de exportación de rollos (ExportRollMappingCommand) que no están
integrados al flujo principal de inventario ni documentados.
5.6 Glosario de términos del negocio
Incluye términos específicos del ERP o del negocio que no son obvios para alguien nuevo.
Término
Significado
SAM
Standard Allowed Minutes — tiempo estándar permitido
para ejecutar una operación de producción. Es el
indicador clave para calcular el costo de mano de obra
por unidad producida.
SAM base
Tiempo SAM sin ajustes por factores externos. Se
almacena en sam_base en product_sheet_operations.
SAM unitario
SAM ajustado por el factor de la base de tela.
Representa el tiempo real por unidad.
SAM total operación
SAM unitario multiplicado por la cantidad de unidades de
la operación.
Ficha Técnica
Documento que describe las especificaciones completas
de un producto: materiales, procesos, operaciones,
costuras, telas e insumos. Modelo: TechnicalSheet.
Sábana de
Producto (ProductSheet)
Registro del producto en producción, con su referencia,
tipo (producto o muestra), base de tela y estado
(completo/incompleto). Es el punto de partida para crear
órdenes de producción.
Listado Operacional Lista de operaciones asignadas a un producto específico
con sus tiempos SAM. Modelo: ProductOperationList.
Orden de
Producción (ProductionOrder)
Instrucción formal para fabricar unidades de un
producto. Tiene un código OP, temporada, destino,
prioridad y unidades. Es el Aggregate Root del dominio
de producción.
OP Abreviatura de Orden de Producción. El
campo op_code identifica la orden.
Sección de operación Clasificación de una operación dentro del proceso
productivo.
Tipos: PREPARACION, ENSAMBLE, TERMINACION.
Modelo: OperationSection.
Base de tela (FabricBase) Tipo de tela base que se usa en el producto. Afecta el
factor de cálculo del SAM unitario.
Insumo Material secundario usado en la confección de una
prenda (hilos, botones, cierres, etc.).
Modelo: TechnicalDataSheetSupply.
Rollo Unidad física de tela recibida en bodega.
Modelo: TechnicalSheetRoll. Se asocia a una ficha
técnica y tiene consumo registrado
en ProductionRollConsumption.
Temporada (season) Identificador de la colección comercial a la que
pertenece una orden de producción (ej. verano 2025,
otoño 2025).
Destino (Destination) Mercado o canal al que va dirigida la producción (ej.
exportación, nacional, e-commerce).
Colección (Collection) Agrupación de productos bajo un mismo concepto de
diseño o línea comercial.
Muestra Tipo especial de hoja de producto (type = 'muestra') que
representa un prototipo antes de la producción en serie.
CxP Cuentas por Pagar — obligaciones de pago pendientes
con proveedores derivadas de facturas de compra
aprobadas.
Nota crédito Documento que reduce el valor de una factura ya
emitida (devoluciones, descuentos posteriores).
Nota débito Documento que incrementa el valor de una factura ya
emitida (ajustes al alza, gastos adicionales).
Comprobante de egreso Documento contable que registra una salida de dinero
de la empresa (pago a proveedor, gasto).
Solicitud de compra Documento interno previo a la orden de compra, donde
se solicita autorización para adquirir bienes o servicios.
Soft delete Eliminación lógica — el registro no se borra físicamente
de la base de datos, solo se marca con deleted_at.
Control de lote (BatchControl) Registro del seguimiento de un lote de producción,
incluyendo novedades y entregas.
Modelos: BatchControlDelivery, BatchControlNovelty.
Sección (Section) Área física o funcional dentro de la planta de producción
a la que se asignan operaciones.
Parte de
prenda (OperationGarmentPart)
División física de una prenda (manga, cuello, bolsillo,
etc.) a la que se asocia una operación específica.
