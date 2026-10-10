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
Con alcance `ver todos`, los permisos de destino limitan qué solicitudes de otros se ven:
- `ver solicitudes compra a gerencia`: ve las dirigidas a gerencia.
- `ver solicitudes compra a logistica`: ve las dirigidas a logística.

Con un solo permiso de destino solo se ven esas (más las propias y las sin destino). Con ambos, o con ninguno, no se restringe.

## Flujo
Borrador → Pendiente (enviar a aprobación) → Aprobada → Procesada (orden de compra generada). Desde Borrador o Pendiente se puede Cancelar; desde Pendiente, Rechazar.
