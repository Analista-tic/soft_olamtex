# Módulo de Configuración

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Configuración y cómo funciona el sistema de usuarios, roles y permisos.

---

## 1. ¿Qué hace este módulo?

El módulo de Configuración es el **panel de administración** del sistema. Aquí se gestiona:

- Quiénes pueden entrar al sistema (**usuarios**).
- Qué puede hacer cada uno (**roles y permisos**).
- La información de la empresa (**datos básicos**).
- Colores y apariencia del sistema.
- Notificaciones del sistema.
- El **registro de auditoría** (historial de quién hizo qué y cuándo).

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Administrador del sistema | Crear usuarios, asignar roles, configurar la empresa |
| Gerencia | Revisar el log de auditoría |
| Cualquier usuario | Cambiar su perfil, contraseña y apariencia personal |

---

## 3. Pantallas principales

### Configuración del sistema (`resources/js/pages/Configuration/`)

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Usuarios | `Configuration/Users/` | Crear, editar y desactivar usuarios |
| Roles | `Configuration/Roles/` | Crear roles y asignar permisos |
| Datos básicos de empresa | `Configuration/BasicData/` | Nombre, NIT, logo, dirección, email de la empresa |
| Colores del sistema | `Configuration/Colors/` | Colores del catálogo (compartido con Datos Maestros) |
| Notificaciones | `Configuration/Notifications/` | Gestionar notificaciones del sistema |

### Configuración personal (`resources/js/pages/settings/`)

| Pantalla | ¿Qué hace? |
|----------|------------|
| `Profile.vue` | Nombre, email y foto de perfil del usuario actual |
| `Password.vue` | Cambiar contraseña |
| `Appearance.vue` | Tema visual (claro/oscuro) y color de acento |

---

## 4. Piezas clave del módulo

### 4.1 Usuarios (`users`)
Cada persona que accede al sistema tiene un usuario.

Datos principales:
- Nombre completo, email (es el login)
- Contraseña (cifrada, nunca se guarda en texto plano)
- Área a la que pertenece (`area_id`)
- Avatar (foto de perfil)
- Preferencias visuales: tema (`theme_appearance`) y color (`theme_color`)
- Roles asignados (pueden tener más de uno)

### 4.2 Roles y permisos
El sistema usa la librería **Spatie Laravel Permission** para gestionar roles y permisos.

Cómo funciona:
```
USUARIO
    → tiene uno o más ROLES
        → cada ROL tiene uno o más PERMISOS
            → cada PERMISO controla una acción específica del sistema
```

Ejemplos de permisos:
- `facturas.crear` — puede crear facturas de venta
- `inventario.ajustar` — puede hacer ajustes de inventario
- `usuarios.ver` — puede ver la lista de usuarios

> **Importante:** si un usuario no tiene el permiso, el botón o la pantalla simplemente no aparece. El backend también valida los permisos independientemente del frontend.

### 4.3 Configuración de empresa (`company_settings`)
Información oficial de la empresa que aparece en documentos (facturas, órdenes, etc.).

Datos: nombre, NIT, teléfono, email, email de contabilidad, sitio web, dirección, municipio, departamento, descripción, logo.

### 4.4 Registro de auditoría (`audit_logs`)
Guarda un historial de **quién hizo qué y cuándo** en el sistema.

Cada vez que alguien crea, edita o elimina un registro importante, queda guardado en el log de auditoría con:
- Qué modelo/entidad fue afectada
- Qué acción se realizó (crear, actualizar, eliminar)
- Los datos antes y después del cambio
- El usuario que lo hizo
- La fecha y hora exacta

> Esto es muy útil para investigar problemas: si algo cambió sin que nadie lo recuerde, el log de auditoría lo registra.

### 4.5 Áreas (`areas`)
Las áreas son departamentos o divisiones internas de la empresa. Los usuarios se asignan a un área (ej: "Producción", "Contabilidad", "Ventas").

### 4.6 Notificaciones (`notifications`)
Alertas internas que el sistema envía a los usuarios (ej: "Tu solicitud de compra fue aprobada", "Hay un rollo pendiente de inspección").

---

## 5. Sistema de roles: cómo agregar o quitar permisos

Cuando hay que darle acceso a algo nuevo a un usuario:
1. Ir a **Configuración → Roles**.
2. Editar el rol del usuario o crear uno nuevo.
3. Marcar los permisos correspondientes.
4. Guardar — el cambio aplica de inmediato.

Si el acceso es solo para un usuario y no para todo el rol, se puede asignar un permiso directamente al usuario (aunque en la práctica es mejor hacerlo por roles).

---

## 6. Reglas de negocio importantes (¡no romper!)

1. **Nunca eliminar un usuario, solo desactivarlo** — los usuarios están vinculados a miles de registros históricos. Eliminarlos rompe la trazabilidad.
2. **El logo y el NIT de la empresa se imprimen en los documentos** — si se cambian, los documentos futuros reflejarán el nuevo valor. Los documentos ya emitidos no cambian.
3. **Los roles y permisos se validan en el backend** — aunque el frontend oculte botones, el servidor también verifica el permiso antes de ejecutar cualquier acción.
4. **El log de auditoría no se puede borrar desde la interfaz** — es un registro protegido para garantizar la trazabilidad.
5. **La contraseña nunca se guarda en texto plano** — siempre está cifrada con bcrypt. Si alguien "pierde" la contraseña, se resetea, no se recupera.

---

## 7. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `users` | Usuarios del sistema |
| `roles` | Roles disponibles |
| `permissions` | Permisos del sistema |
| `model_has_roles` | Qué roles tiene cada usuario |
| `model_has_permissions` | Permisos directos por usuario |
| `role_has_permissions` | Qué permisos tiene cada rol |
| `company_settings` | Información de la empresa |
| `audit_logs` | Historial de cambios en el sistema |
| `areas` | Áreas o departamentos de la empresa |
| `notifications` | Notificaciones internas |

---

## 8. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Settings/User.php` | Modelo de usuario — incluye roles y área |
| `app/Models/Settings/Role.php` | Modelo de rol (extiende Spatie) |
| `app/Models/Settings/CompanySetting.php` | Modelo de configuración de empresa |
| `app/Models/Settings/AuditLog.php` | Modelo del log de auditoría |
| `app/Models/Settings/Area.php` | Modelo de áreas |
| `app/Http/Controllers/Settings/UserController.php` | Lógica de usuarios |
| `app/Http/Controllers/Settings/RoleController.php` | Lógica de roles y permisos |
| `app/Http/Controllers/Settings/CompanySettingsController.php` | Lógica de configuración de empresa |
| `app/Http/Controllers/Settings/AuditLogController.php` | Lógica del log de auditoría |
| `resources/js/pages/Configuration/Users/Index.vue` | Pantalla de usuarios |
| `resources/js/pages/Configuration/Roles/Index.vue` | Pantalla de roles |

---

## Reglas de notificaciones (solo superadmin)

**Ruta:** `/configuracion/reglas-notificaciones` · **Pantalla:** `Configuration/NotificationRules/Index.vue` · **Acceso:** rol `superadmin` (el rol se crea con la migración `2026_09_24_000002` y se asigna al usuario `superadmin@softolamtex.local`, que conserva también `admin`).

Antes cada aviso decidía por su cuenta a quién notificar (unos por rol, otros por área, otros por permiso, otros por personas concretas). Ahora todos los avisos pasan por un único punto (`NotificationDispatcher`) y esta pantalla define, por evento:

| Qué se configura | Significado |
|---|---|
| **Cuándo** | Cada evento del sistema (catálogo en `NotificationEventRegistry`) se puede activar o apagar. |
| **Cómo** | Campana del sistema, correo o ambos. |
| **A quién** | Lista de destinatarios que se suman sin repetir personas: **Rol**, **Área**, **Persona**, o **Automático** (quien corresponda según el caso: el solicitante, el encargado de compra, quien creó la factura, las áreas del proceso de una novedad). Cada destinatario puede llevar un filtro "solo con el permiso X". |
| **Excluir a quien ejecuta la acción** | Evita avisarle a alguien de algo que hizo él mismo. |

**Datos:** tablas `notification_events` y `notification_event_recipients`. Los valores iniciales salen del catálogo y replican el comportamiento anterior; desde que se crea la fila manda lo configurado. Los cambios quedan en el registro de auditoría.

**Eventos incluidos:** solicitud de compra enviada / respondida, orden creada, orden pendiente de aprobación\*, diferencia de factura pendiente / aprobada\* / rechazada, factura rechazada\*, recepción de tela completa\*, solicitud de inventario o insumos creada, novedad en control de lote. (\* nuevos, apagados por defecto.)

**Para el código:** un módulo que quiera avisar no calcula destinatarios; llama `app(NotificationDispatcher::class)->dispatch('clave_evento', $notificacion, ['actor' => $usuario, ...destinatarios automáticos])`. Para un evento nuevo se agrega su definición en `NotificationEventRegistry` y la clase de notificación usa el trait `UsesConfiguredChannels`.
