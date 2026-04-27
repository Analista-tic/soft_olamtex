# Roadmap de Conocimientos para Empezar en el Proyecto

**Fecha:** 20 de abril de 2026  
**Propósito:** guía práctica para una persona sin contexto previo del sistema.

---

## 1) Objetivo real del onboarding

Que una persona nueva pueda, en pocas semanas, crear cambios seguros y mantenibles sin romper datos, seguridad ni trazabilidad.

---

## 2) Reglas críticas (no negociables)

### 2.1 Datos de negocio

- Todo dato clave va a base de datos relacional.
- No se permite guardar datos críticos solo en frontend o campos no estructurados.
- Cada cambio funcional debe incluir estructura, validación y relación de datos.

### 2.2 Lógica

- Reglas de negocio en backend.
- Interfaz enfocada en captura/visualización.
- No duplicar reglas entre frontend y backend.

### 2.3 Seguridad y trazabilidad

- Toda operación debe validar permisos.
- Toda acción relevante debe dejar evidencia (logs/auditoría).
- Toda acción del usuario debe mostrar resultado (éxito, advertencia o error).

---

## 3) Tecnologías del proyecto (qué debes reconocer)

### 3.1 Backend

- Laravel 12 (framework principal).
- PHP 8.2 (lenguaje del backend).
- SQL relacional (persistencia de negocio).

### 3.2 Frontend

- Vue 3 (interfaz de usuario).
- Inertia.js (puente entre backend y frontend).
- TypeScript (tipado en frontend).
- Tailwind CSS (estilos).

### 3.3 Soporte de desarrollo

- Vite (build y entorno de desarrollo frontend).
- Pinia (estado global frontend).
- Ziggy (uso de rutas del backend en frontend).
- Pest/PHPUnit (pruebas backend).

---

## 4) Estructura de datos: lo mínimo que debes aplicar

### 3.1 Modelo base por entidad de negocio

Cada entidad principal debe tener, como mínimo:

- identificador único,
- campos de negocio obligatorios,
- estado activo/inactivo cuando aplique,
- fechas de creación/actualización,
- referencia de usuario creador/modificador cuando sea relevante.

### 3.2 Relaciones

- Usa llaves foráneas para conectar entidades.
- Define cardinalidad clara: 1:1, 1:N o N:N.
- Evita datos repetidos: referencia en lugar de duplicación.

### 3.3 Integridad y consistencia

- Campos obligatorios con validación de backend.
- Índices en campos de búsqueda frecuente.
- Restricciones para evitar datos huérfanos o inválidos.

### 3.4 Cambios de esquema

Al agregar o modificar datos:

1. Crear migración.
2. Actualizar modelo y relaciones.
3. Ajustar validaciones.
4. Probar lectura/escritura real.
5. Documentar impacto.

---

## 5) Carpetas del proyecto: qué vive en cada lugar

Solo lo esencial para trabajar.

### 5.1 Estructura backend

- `app/Models`: entidades y relaciones de datos.
- `app/Services`: lógica de negocio y reglas de proceso.
- `app/Http/Controllers`: entrada HTTP, validación inicial, llamado a servicios.
- `app/Http/Requests`: validaciones de entrada.
- `database/migrations`: estructura de base de datos.
- `database/seeders`: datos base para pruebas/local.
- `routes/`: definición de endpoints y acceso.
- `resources/js`: interfaz (pantallas, formularios, flujos de usuario).
- `docs/`: documentación funcional/técnica.
- `tests/`: pruebas automáticas.

Regla práctica: si no sabes dónde poner algo, pregúntate si es dato, regla o interfaz; ubícalo en la capa correspondiente.

### 5.2 Estructura frontend con Inertia (composición)

- `resources/js/app.ts`: arranque cliente de Inertia.
- `resources/js/ssr.ts`: arranque para renderizado del lado servidor (SSR).
- `resources/js/pages/`: pantallas (cada vista que devuelve el backend).
- `resources/js/layouts/`: estructuras base de página (envolturas compartidas).
- `resources/js/components/`: componentes reutilizables de interfaz.
- `resources/js/composables/`: lógica reutilizable del lado frontend.
- `resources/js/stores/`: estado global.
- `resources/js/services/`: llamadas/servicios de frontend.
- `resources/js/types/`: tipos/interfaces.
- `resources/js/domain/`: lógica o modelos de dominio en frontend.
- `resources/js/lib/`: utilidades comunes.
- `resources/js/data/`: catálogos o datos estáticos controlados.

Cómo se conecta Inertia de forma simple:

1. Backend recibe petición y valida permisos.
2. Backend responde una página Inertia con datos.
3. Frontend renderiza la vista ubicada en `pages/`.
4. Formularios envían cambios al backend y muestran feedback.

---

## 6) Flujo de desarrollo recomendado (paso a paso)

1. Entender requerimiento de negocio en una frase clara.
2. Diseñar datos (tablas, relaciones, reglas).
3. Implementar backend (validación + lógica + permisos).
4. Implementar interfaz con mensajes claros al usuario.
5. Probar flujo completo (casos exitosos y errores).
6. Documentar qué cambió y por qué.

---

## 7) Buenas prácticas de ingeniería (alto impacto)

- Cambios pequeños y enfocados por ticket.
- Evitar “soluciones rápidas” que rompan arquitectura.
- No mezclar código nuevo con refactor grande no solicitado.
- Nombrar variables, funciones y rutas de forma clara.
- Registrar errores con contexto útil para depurar.
- Escribir primero para que otro pueda mantenerlo después.

Buenas prácticas puntuales para estructura:

- No poner reglas de negocio complejas dentro de controladores.
- No hacer consultas directas desde la capa visual si existe endpoint definido.
- No duplicar validación crítica solo en frontend.
- Mantener nombres de carpetas/archivos consistentes con su responsabilidad.

---

## 8) IA como herramienta de trabajo (uso inteligente)

### 7.1 Para qué sí usar IA

- Acelerar borradores de código repetitivo.
- Generar checklist de validación y pruebas.
- Proponer refactors pequeños y seguros.
- Ayudar a documentar decisiones técnicas.

### 7.2 Para qué no usar IA sin revisión

- Definir reglas de negocio sin validación humana.
- Modificar datos críticos sin revisar impacto completo.
- Aceptar código sugerido sin pruebas.

### 7.3 Protocolo recomendado de uso

1. Pedir a la IA una propuesta corta y concreta.
2. Revisar arquitectura, seguridad y datos.
3. Ejecutar pruebas.
4. Ajustar manualmente.
5. Documentar decisión final.

---

## 9) Roadmap de aprendizaje (6 semanas)

### Semana 1 — Entorno y lectura del sistema

- Levantar proyecto local.
- Entender un flujo completo de punta a punta.
- Identificar carpetas clave y responsabilidades.

### Semana 2 — Datos y backend

- Crear/ajustar una entidad con relaciones y validaciones.
- Implementar permisos y manejo de errores.

### Semana 3 — Interfaz y experiencia

- Construir un formulario funcional.
- Mostrar resultados claros para éxito/error.

### Semana 4 — Integración completa

- Entregar una mejora vertical (datos + backend + interfaz).

### Semana 5 — Calidad

- Ejecutar pruebas, revisar estilo y corregir hallazgos.

### Semana 6 — Autonomía

- Resolver un bug real, justificar causa raíz y documentar solución.

---

## 10) Checklist de entrada rápida

### Primer día

- Accesos listos (repositorio, base de datos, entorno).
- Proyecto ejecutando en local.

### Primeros 3 días

- Entender dos flujos de negocio.
- Entender dónde vive cada tipo de código.

### Primera semana

- Entregar primer cambio pequeño con revisión.

---

## 11) Definición de “listo para producción”

Un cambio está listo cuando:

- los datos quedan correctos y trazables,
- la seguridad está validada,
- el usuario recibe feedback claro,
- las pruebas relevantes pasan,
- la documentación mínima está actualizada.

