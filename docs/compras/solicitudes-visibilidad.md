# Solicitudes de compra: visibilidad y solicitante

Implementación: `PurchaseRequestService::applyVisibility` y `prepareData`.

## Solicitante
Siempre es el usuario logueado que crea la solicitud. No se puede elegir otro: el backend ignora cualquier `requester_user_id` recibido y el formulario muestra el campo en solo lectura.

Otros campos: `solicited_to` (área que aprueba: `gerencia` / `logistica`) y `buyer_user_id` (encargado de la compra).

## Quién ve qué
| Estado | Solicitante | Otros usuarios |
|---|---|---|
| Borrador | Sí | **No**, ni con `ver todos` |
| Pendiente en adelante | Según su alcance | Según alcance (`ver propios` / `ver area` / `ver todos`) y `solicited_to` |

`admin` y `logistica` con `ver todos` solo ven solicitudes de otros dirigidas a su área (o sin área) y que no sean borrador.

## Flujo
Borrador → Pendiente (enviar a aprobación) → Aprobada → Procesada (orden de compra generada). Desde Borrador o Pendiente se puede Cancelar; desde Pendiente, Rechazar.
