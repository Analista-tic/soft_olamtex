# Estrategia de salida operativa de Olamtex

## Objetivo

Definir una estrategia práctica para poner Olamtex en operación sin intentar encender todo al mismo tiempo. La meta no es liberar “todo el ERP”, sino asegurar primero el flujo que más valor operativo genera, reducir el riesgo de caída y dar un marco claro para soporte, incidentes y adopción.

---

## 1. Principio rector: salir por camino crítico, no por cobertura total

En una salida a producción real, el error más común es intentar activar todos los módulos y todas las casuísticas desde el día 1. Eso suele producir tres efectos negativos:

1. El equipo operativo se confunde.
2. Soporte se satura con incidencias mezcladas.
3. El sistema pierde credibilidad antes de estabilizarse.

La recomendación para Olamtex es liberar por **camino crítico**:

- primero lo que mantiene viva la operación diaria,
- después lo que mejora control,
- y al final lo que amplía cobertura o automatización.

---

## 2. Happy Path propuesto para Olamtex

## ¿Qué sí debe funcionar desde el arranque?

Con base en los módulos ya presentes en el sistema, el camino crítico más sólido hoy es este:

### Camino crítico A: alta y preparación de la orden

1. **Creación del programador**
   - Registro de la orden/lote base.
   - Definición de referencia, prioridad, cantidades y contexto comercial.
   - Este paso alimenta el resto del flujo.

2. **Completar ficha técnica de la OP**
   - Información básica.
   - Listado operacional.
   - Procesos.
   - Materia prima.
   - Rollos.
   - Hilos.
   - Insumos.
   - Información adicional.
   - Despiece de corte.

3. **Validación mínima para producción**
   - La OP queda suficientemente preparada para que planta pueda trabajar sin depender de memoria, WhatsApp o archivos dispersos.

### Camino crítico B: seguimiento operativo del lote

4. **Control de lotes**
   - Visualizar en qué proceso va cada orden.
   - Mover la orden entre procesos.
   - Gestionar prioridades.
   - Registrar fecha estimada.
   - Registrar entregas.
   - Gestionar novedades/devoluciones.

5. **Consulta operativa por supervisores**
   - Saber qué está pendiente.
   - Saber qué se entregó.
   - Saber qué está bloqueado por novedad.

### Conclusión del Happy Path

Si Olamtex arranca con estos dos bloques estabilizados:

- **Programador + Ficha Técnica**, y
- **Control de Lotes**

ya tiene cubierto el 80% del valor operativo inicial.

No porque sea todo el sistema, sino porque es el flujo que conecta:

- planeación,
- preparación técnica,
- paso a producción,
- y seguimiento del estado real del lote.

---

## 3. Qué dejar fuera del arranque inicial

Para reducir riesgo, no todo debe entrar al día 1 como obligatorio.

Se recomienda clasificar en tres grupos:

### Grupo 1: obligatorio en salida

- Programador.
- Ficha técnica.
- Listado operacional asociado a ficha técnica.
- Procesos/resumen técnico necesario para producir.
- Control de lotes.
- Alertas visibles para acciones críticas.
- Permisos básicos de acceso por rol.

### Grupo 2: activación controlada después de estabilizar

- Novedades avanzadas con reglas más complejas.
- Devoluciones con más validaciones.
- Reportes gerenciales no críticos.
- Auditorías de uso para análisis fino.
- Automatizaciones no esenciales.

### Grupo 3: diferible o con operación híbrida temporal

- Integraciones periféricas.
- Variantes poco frecuentes.
- Casos históricos especiales.
- Parametrizaciones sofisticadas que el usuario aún no domina.

---

## 4. Feature Toggles: obligatorios para una salida sana

## ¿Por qué?

Porque una salida a producción no debe obligar a desinstalar código ni a hacer rollback completo si falla un subflujo. El objetivo del toggle es apagar una capacidad puntual sin tumbar la operación principal.

## Recomendación para Olamtex

Definir toggles por módulo o subflujo, no por pantalla completa solamente.

### Toggles sugeridos

| Toggle | Objetivo | Estado recomendado en salida inicial |
|---|---|---|
| `programador_enabled` | Habilita creación/edición del programador | ON |
| `ficha_tecnica_enabled` | Habilita flujo principal de ficha técnica | ON |
| `ficha_tecnica_despiece_enabled` | Habilita despiece de corte | ON |
| `control_lotes_enabled` | Habilita seguimiento operativo por proceso | ON |
| `control_lotes_novedades_enabled` | Habilita novedades/devoluciones | ON controlado |
| `control_lotes_historial_enabled` | Habilita historial detallado | ON si ya está estable |
| `ordenes_produccion_detalle_enabled` | Habilita detalle extendido de OP | Opcional |
| `compras_integradas_enabled` | Obliga uso integrado con compras | OFF al inicio si no es crítico |
| `inventario_dependiente_enabled` | Obliga validaciones cruzadas fuertes con inventario | OFF controlado |

## Regla de uso

Cada toggle debe tener tres propósitos claros:

1. **Encender gradual** una funcionalidad.
2. **Apagar rápido** una funcionalidad problemática.
3. **Aislar fallas** sin afectar el camino crítico.

## Regla operativa clave

Si una funcionalidad no pertenece al Happy Path, debe poder apagarse sin detener la empresa.

---

## 5. Semáforo de incidencias

Durante las primeras semanas no se debe tratar todo error igual. Se necesita triaje.

## Incidencia roja

### Definición

Error que detiene operación o pone en riesgo información crítica.

### Ejemplos

- No se puede crear un programador.
- No se puede abrir o guardar la ficha técnica de una OP crítica.
- No se puede mover un lote en control de lotes.
- La información se pierde al guardar.
- El sistema impide continuar una operación diaria clave.

### Acción

- Atención inmediata.
- Prioridad absoluta.
- Mitigación el mismo día.
- Si aplica, apagar el subflujo con toggle.
- Comunicación directa a superusuarios y responsables.

## Incidencia amarilla

### Definición

Error que permite continuar, pero con fricción, riesgo operativo moderado o uso manual temporal.

### Ejemplos

- Una validación secundaria falla, pero el dato se puede registrar por otra vía.
- Una tabla no muestra un dato auxiliar, pero el lote sigue avanzando.
- El historial o una vista de detalle no carga correctamente.
- Una alerta visual no aparece como debería.

### Acción

- Registrar.
- Priorizar en ventana corta.
- Resolver sin interrumpir el flujo productivo.
- Monitorear si escala a rojo por recurrencia.

## Incidencia verde

### Definición

Error cosmético, mejora UX o ajuste menor sin impacto real en continuidad operativa.

### Ejemplos

- Textos inconsistentes.
- Alineación visual.
- Ordenamiento no ideal.
- Etiquetas mejorables.

### Acción

- Agendar.
- Resolver en lote.
- Nunca desplazar una roja o amarilla urgente.

---

## 6. Regla de oro de soporte en arranque

**No se debate severidad con el usuario en caliente.**

La severidad se clasifica internamente así:

- Si la empresa se detiene: roja.
- Si la empresa sigue, pero con dolor: amarilla.
- Si solo incomoda: verde.

---

## 7. Operatividad de la empresa: qué no puede caerse

En un arranque, la pregunta correcta no es “¿qué módulo está bonito?”, sino:

## ¿Qué procesos no pueden quedar sin apoyo de TICS?

Para Olamtex, hoy los procesos que más comprometen continuidad si fallan son:

### 1. Alta y ajuste del programador

Si el equipo no puede registrar o ajustar el programador:

- no hay base ordenada para trabajar,
- se pierde prioridad real,
- se descoordina producción,
- y reaparece el manejo informal por chat o archivo suelto.

### 2. Preparación técnica de la OP

Si la ficha técnica no queda usable:

- producción trabaja con vacíos,
- corte y procesos siguientes toman decisiones con información incompleta,
- aumentan retrabajos,
- y la dependencia de memoria del equipo vuelve a crecer.

### 3. Seguimiento real del lote

Si control de lotes falla:

- no se sabe dónde está la orden,
- no se prioriza bien,
- no se detecta el cuello de botella,
- y la gerencia pierde trazabilidad básica del avance.

### 4. Gestión de novedades

Si la novedad no se registra o no se resuelve de forma visible:

- el lote parece avanzar cuando en realidad está bloqueado,
- se deteriora la confianza en el sistema,
- y soporte termina trabajando a ciegas.

---

## 8. Modelo operativo de superusuarios

Para que el arranque funcione, no basta con “capacitar a todos”. Se necesita una red corta de usuarios fuertes.

## Perfil recomendado

Nombrar al menos un superusuario por frente crítico:

- **Comercial / Programación**
- **Ficha técnica / Desarrollo**
- **Producción / Control de lotes**
- **Soporte TICS**

## Responsabilidades del superusuario

### Antes de salida

- Validar que el flujo del área se puede ejecutar completo.
- Probar casos reales, no solo demos.
- Identificar campos ambiguos o pasos sobrantes.
- Confirmar qué sí será obligatorio y qué quedará fuera temporalmente.

### Durante salida

- Ser primer filtro funcional.
- Ayudar al usuario final sin escalar todo a desarrollo.
- Distinguir si el problema es capacitación, dato o bug real.
- Documentar incidencias con contexto suficiente.

### Después de salida

- Consolidar mejoras repetitivas.
- Identificar fricciones del flujo.
- Proponer endurecimiento gradual de reglas.

---

## 9. Plan de salida sugerido

## Fase 0: validación previa

Duración recomendada: 3 a 5 días hábiles.

### Objetivo

Asegurar que el Happy Path corre de punta a punta con casos reales.

### Checklist

- Crear programadores reales.
- Abrir ficha técnica de OP reales.
- Guardar materia prima, rollos, hilos, insumos y despiece.
- Confirmar que la información persiste.
- Mover órdenes por control de lotes.
- Registrar al menos una entrega y una novedad.
- Verificar alertas y mensajes operativos.

## Fase 1: salida controlada

Duración recomendada: 1 semana.

### Objetivo

Operar con usuarios reales, pero con acompañamiento intensivo.

### Reglas

- Solo se obliga el Happy Path.
- Todo lo demás queda opcional o por toggle.
- Reunión corta diaria de incidencias.
- Corte diario de pendientes rojo/amarillo/verde.

## Fase 2: estabilización

Duración recomendada: 2 semanas.

### Objetivo

Reducir dependencia del equipo TICS y consolidar disciplina operativa.

### Meta

- Menos incidencias rojas.
- Menos consultas repetitivas.
- Más resolución por superusuario.
- Activación gradual de capacidades no críticas.

## Fase 3: expansión funcional

### Objetivo

Encender módulos o reglas adicionales sin poner en riesgo la operación ya estabilizada.

Ejemplos:

- validaciones más estrictas,
- integraciones adicionales,
- reportes de gestión,
- automatizaciones complementarias.

---

## 10. Criterios de éxito del arranque

La salida es exitosa si al finalizar la etapa inicial se cumple esto:

1. El programador se usa de forma real y consistente.
2. La ficha técnica queda guardada y consultable sin pérdida de información.
3. El estado del lote se consulta y actualiza desde el sistema.
4. Las incidencias rojas se resuelven rápido o se contienen con toggles.
5. Los superusuarios absorben el soporte funcional de primer nivel.
6. El negocio no depende de volver a archivos paralelos para operar el flujo principal.

---

## 11. Recomendación ejecutiva final

Para Olamtex, la salida más segura no es “activar todo”, sino:

- **hacer obligatorio solo el camino crítico**,
- **proteger el resto con feature toggles**,
- **operar con semáforo de incidencias**,
- **apoyarse en superusuarios**,
- y **estabilizar primero la continuidad operativa antes de ampliar cobertura**.

Si esto se respeta, el sistema gana confianza en planta y en administración. Si no se respeta, el riesgo más alto no es técnico: es que la organización vuelva a operar por fuera del sistema.
