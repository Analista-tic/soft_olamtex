# 🚀 Guía de Optimización: Acelera la carga de soft_olamtex

## Problema Identificado
✗ Demora 10-20 segundos en estar lista  
✗ El backend (Laravel) tarda en responder

## Causa Raíz
El comando `composer run dev` ejecuta **3 procesos en paralelo**:
```
1. php artisan serve       ✅ Necesario
2. php artisan queue:listen ⚠️ Consume recursos innecesarios en desarrollo
3. npm run dev              ✅ Necesario
```

El **queue:listen** es útil solo cuando necesitas procesar jobs en background (emails, notificaciones, etc). Para desarrollo simple, es innecesario.

---

## Solución: Modo Desarrollo Rápido

### Opción 1: Comando Rápido (RECOMENDADO)
```bash
composer run dev:fast
```

**Beneficios:**
- ⚡ Inicia en 5-7 segundos (vs 10-20 segundos)
- 📊 Menos uso de CPU/RAM
- 🔧 Perfecto para trabajo frontend/backend normal

---

### Opción 2: Si Necesitas Queue Listener (Con jobs activos)
```bash
composer run dev
```

Úsalo solo si:
- Necesitas procesamiento asíncrono  
- Trabajas con emails, notificaciones, pdfs en background
- Estás testeando comportamiento de queues

---

### Opción 3: Optimización Previa (Una sola vez)

```bash
# Ejecuta estas órdenes una sola vez al empezar sesión:
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate
php artisan cache:clear
```

Luego, ejecuta:
```bash
composer run dev:fast
```

---

## Comparación de Tiempos

| Comando | Inicio | CPU | Recomendado |
|---------|--------|-----|------------|
| `composer run dev` | 15-20s | Alto | ❌ Desarrollo normal |
| `composer run dev:fast` | 5-7s | Bajo | ✅ Desarrollo normal |
| `composer run dev:ssr` | 20-30s | Muy Alto | ⚠️ Solo si necesitas SSR |

---

## Pasos para Implementar

### 1️⃣ Actualización Inmediata (Ya Hecho)
✅ composer.json actualizado con `dev:fast`

### 2️⃣ Optimiza tu PC
```bash
# Limpiar caché
php artisan optimize:clear

# Luego inicia
composer run dev:fast
```

### 3️⃣ Verifica que funcione
- Abre http://127.0.0.1:8000
- Deberías ver la app en 5-10 segundos
- Los formularios deben responder rápido

---

## Notas Importantes

⚠️ **Si necesitas usar jobs/queues:**
- Para producción, mantén `php artisan queue:work`
- Para desarrollo con queues, usa `composer run dev`

💡 **Si sigue lento:**
1. Revisa si hay migraciones pending: `php artisan migrate`
2. Verifica base de datos: `php artisan tinker` → `\App\Models\User::count()`
3. Comprueba que Vite no está compilando assets (ver consola npm)

📊 **Monitorea performance:**
- Abre DevTools (F12) → Network para ver qué tarda
- Revisa "Waterfall" de peticiones HTTP
- Los datos de formularios (`/data`) deben tardar <1 segundo

---

## Resumen

| Acción | Comando |
|--------|---------|
| **Desarrollo rápido (USAR POR DEFECTO)** | `composer run dev:fast` |
| Si necesitas queue listener | `composer run dev` |
| Si necesitas SSR | `composer run dev:ssr` |
| Limpiar todo y optimizar | `php artisan optimize:clear` |

---

✨ Ahora ejecuta:
```bash
composer run dev:fast
```

¡Debería estar listo en 5-10 segundos en lugar de 20! 🚀
