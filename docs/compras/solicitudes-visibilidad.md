# Solicitudes de compra: visibilidad y solicitante

Implementación: `PurchaseRequestService::applyVisibility` y `prepareData`.

## Solicitante
Siempre es el usuario logueado que crea la solicitud. No se puede elegir otro: el backend ignora cualquier `requester_user_id` recibido y el formulario muestra el campo en solo lectura.

Otros campos: `solicited_to` (área que aprueba: `gerencia` / `logistica`) y `buyer_user_id` (encargado de la compra).

## Quién ve qué
| Estado | Solicitante | Otros usuarios |
|---|---|---|
| Borrador | Sí | **No**, ni con `ver todos` |
| Pendiente en adelante | Según su alcance | Según alcance (`ver propios` / `ver area` / `ver todos`) |

La visibilidad depende solo de permisos, sin filtros por nombre de rol.

### Destino (`solicited_to`)
Los permisos de destino **suman** visibilidad, nunca la quitan:
- `ver solicitudes compra a gerencia`: además de su alcance, ve las dirigidas a gerencia.
- `ver solicitudes compra a logistica`: además de su alcance, ve las dirigidas a logística.

Regla: **alcance (propios / área) OR destino permitido**. Con `ver todos` se ve todo, sin importar los permisos de destino.

Un permiso de destino basta para entrar al módulo (middleware de `routes/expenses.php`, menú lateral) aunque el usuario no tenga ningún alcance. En ese caso ve solo las solicitudes de ese destino.

| Permisos del usuario | Ve |
|---|---|
| `ver propios` + `a logistica` (rol logística) | Sus propias + todas las dirigidas a logística |
| solo `a logistica` | Solo las dirigidas a logística |
| `ver todos` (con o sin destinos) | Todas |

Los borradores de otros siguen ocultos aunque vayan a un destino permitido. Cubierto por `tests/Feature/Expenses/PurchaseRequestVisibilityTest.php`.

## Flujo
Borrador → Pendiente (enviar a aprobación) → Aprobada → Procesada (orden de compra generada). Desde Borrador o Pendiente se puede Cancelar; desde Pendiente, Rechazar.
