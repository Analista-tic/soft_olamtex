# Entorno de pruebas en red (LAN)

Guía para que otros dispositivos de la misma red (WiFi/LAN) abran la app que corre en tu PC con Sail (Docker).

## Requisitos

- Docker Desktop instalado y corriendo en Windows.
- Sail levantado (paso 0): el contenedor publica el puerto 80 (app) y 5173 (Vite) en todas las interfaces.
- Dispositivos de prueba conectados a la **misma red** que tu PC.
- PowerShell con permisos de administrador (solo para el paso del firewall).

## 0. Levantar Docker/Sail

El proyecto corre en Docker vía [Laravel Sail](../compose.yaml). Servicios definidos:

| Servicio | Imagen | Puerto host | Descripción |
|---|---|---|---|
| `laravel.test` | Sail (PHP 8.5) | `${APP_PORT:-80}` y `${VITE_PORT:-5173}` | App Laravel |
| `mysql` | mysql:8.4 | `${FORWARD_DB_PORT:-3306}` | Base de datos `soft_olamtex_db` |
| `phpmyadmin` | phpmyadmin | `${FORWARD_PMA_PORT:-8080}` | Administración de la BD |

1. Abre **Docker Desktop** (o espera a que termine de iniciar si ya lo lanzaste) y confirma que el ícono de la ballena esté "Running", no "Starting...".
2. Desde WSL, en la raíz del proyecto:

    ```bash
    ./vendor/bin/sail up -d
    ```

3. Verifica que los tres contenedores estén `Up`/`healthy`:

    ```bash
    ./vendor/bin/sail ps
    ```

Con esto la app ya responde en `http://localhost` y `http://<TU_IP>` (ver paso 1), y phpMyAdmin en `http://localhost:8080`.

> Alias: si tienes `sail` en el PATH de tu shell (WSL), puedes usar `sail up -d` en vez de `./vendor/bin/sail up -d` en todos los comandos de esta guía.

## 1. Obtener la IP de tu PC

En PowerShell de Windows:

```powershell
ipconfig
```

Usa la dirección IPv4 del adaptador **Wi-Fi** (o Ethernet). Ejemplo: `192.168.1.70`.
Puede cambiar si el router asigna IP por DHCP; conviene reservarla en el router.

## 2. Elegir cómo servir el JS

El servidor de desarrollo de Vite escribe `public/hot` con la URL desde la que los navegadores cargan el JS. Si apunta a `localhost:5173`, en otros equipos `localhost` es su propia máquina: la página carga pero sin JS (sin cálculos ni componentes Vue). Hay dos formas de resolverlo.

### Opción A: Vite dev accesible por red (con HMR)

En [vite.config.ts](../vite.config.ts) `server.origin` y `server.hmr.host` deben apuntar a **tu IP LAN** (paso 1):

```ts
server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin: 'http://<TU_IP>:5173',
    hmr: { host: '<TU_IP>' },
    // ...watch.ignored
},
```

Luego levanta Vite:

```bash
sail npm run dev
```

Verifica que `public/hot` contenga `http://<TU_IP>:5173` (no `localhost`):

```bash
cat public/hot
```

Si cambia tu IP, hay que actualizarla en `vite.config.ts` y reiniciar Vite. No hagas commit de una IP personal si otros la usan distinta; cada quien ajusta la suya.

### Opción B: assets compilados (sin Vite dev)

Más estable (no depende de la IP), pero hay que recompilar tras cada cambio de frontend:

```bash
# detener Vite dev si está corriendo (Ctrl+C en su terminal) y luego:
rm -f public/hot
sail npm run build
```

Verifica que el HTML use tu IP y no `localhost:5173`:

```bash
curl -s http://<TU_IP>/login | grep -oE '(src|href)="[^"]*build[^"]*"'
```

## 3. Abrir puertos en el firewall de Windows

El firewall bloquea las conexiones entrantes desde otros equipos. La regla de Docker Desktop solo cubre redes "Públicas"; si tu WiFi es "Privada" hay que crear una regla. El puerto **5173** solo es necesario con la opción A.

En PowerShell **como administrador**:

```powershell
New-NetFirewallRule -DisplayName "Olamtex Dev" -Direction Inbound -Protocol TCP -LocalPort 80,5173 -Action Allow -Profile Private
```

Para ver el tipo de red actual: `Get-NetConnectionProfile`.

Para quitar la regla cuando ya no se necesite:

```powershell
Remove-NetFirewallRule -DisplayName "Olamtex Dev"
```

Docker Desktop ya publica 80 y 5173 en todas las interfaces (`docker ps`); no hace falta `netsh portproxy`.

## 4. Probar desde otro dispositivo

Abre en el navegador: `http://<TU_IP>/` (ejemplo: `http://192.168.1.70/`).

Si la página carga pero no hay cálculos ni componentes, abre F12 → Network y revisa si falla la carga de `:5173` (opción A) o de `/build/` (opción B).

## Volver a desarrollo normal

Con la opción A no hay nada que revertir. Con la opción B, `sail npm run dev` recrea `public/hot`; recuerda que Vite dev solo se ve bien en red si el paso 2A está configurado.

## Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| No conecta desde otro dispositivo | Firewall de Windows | Paso 3 |
| No conecta aun con firewall abierto | Aislamiento de clientes en el router (AP isolation) | Desactivarlo o usar otra red |
| Página carga pero sin JS / cálculos (solo en otros equipos) | `public/hot` apunta a `localhost:5173` | Paso 2 (A o B) |
| Falla `:5173` desde otro equipo | Firewall sin puerto 5173 o IP vieja en `vite.config.ts` | Paso 3 / actualizar IP |
| Dejó de funcionar de un día para otro | La IP cambió | Revisar IP (paso 1) |
| Redirige a `localhost` | `APP_URL` en `.env` | Poner `APP_URL=http://<TU_IP>` y `sail artisan config:clear` |
| `Docker or Podman is not running` al hacer `sail up` | Docker Desktop no ha terminado de iniciar | Abrir Docker Desktop, esperar a "Running" y reintentar |
| `Cannot connect to the Docker daemon` | Docker Desktop cerrado o WSL sin integración habilitada | Abrir Docker Desktop → Settings → Resources → WSL Integration |

## Advertencias

- El entorno usa la **misma base de datos de desarrollo** (`soft_olamtex_db`): lo que se haga desde la red modifica esos datos.
- Solo expone la app a tu red local, no a internet. Para acceso externo se necesita un túnel (Cloudflare Tunnel, ngrok) o un servidor de staging.
- No dejar la regla de firewall abierta en redes que no sean de confianza.
