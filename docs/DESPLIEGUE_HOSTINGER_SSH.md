# Instructivo de Despliegue en Hostinger (SSH) — Subdominio

**Proyecto:** soft_olamtex  
**Stack:** Laravel 12 · PHP ^8.2 · Vue 3 · Inertia.js · Vite  

---

## Requisitos previos

| Requisito | Detalle |
|---|---|
| Plan Hostinger | Business Web Hosting o superior (soporte SSH) |
| PHP | 8.2 o superior habilitado en el panel |
| MySQL | Base de datos creada desde el panel de Hostinger |
| Subdominio | Creado desde hPanel antes de iniciar |
| Git | Repositorio del proyecto accesible (GitHub, GitLab, etc.) |

---

## 1. Crear el subdominio en hPanel

1. Inicia sesión en [hpanel.hostinger.com](https://hpanel.hostinger.com).
2. Ve a **Sitios Web → Administrar → Subdominios**.
3. Clic en **Crear subdominio**.
4. Ingresa el nombre (ej. `app`) → quedará como `app.tudominio.com`.
5. Cambia la **raíz del documento** a: `/public_html/app.tudominio.com/public`  
   *(La carpeta `public` de Laravel debe ser la raíz pública del subdominio.)*
6. Guarda.

---

## 2. Habilitar acceso SSH

1. En hPanel: **Avanzado → SSH**.
2. Activa el acceso SSH y anota:
   - Host: `ssh.hostinger.com` (o el que indique tu panel)
   - Puerto: `65002` (varía según plan)
   - Usuario: tu usuario de hosting
3. Agrega tu clave pública SSH (recomendado) o usa contraseña.

**Conectarse desde tu terminal local:**
```bash
ssh usuario@ssh.hostinger.com -p 65002
```

---

## 3. Estructura de directorios en servidor

```
/home/usuario/
└── public_html/
    └── app.tudominio.com/     ← raíz del proyecto Laravel
        ├── app/
        ├── bootstrap/
        ├── config/
        ├── database/
        ├── resources/
        ├── routes/
        ├── storage/
        ├── vendor/
        └── public/            ← raíz pública del subdominio
```

> **Nunca** apuntes el subdominio a la carpeta raíz del proyecto, siempre a `/public`.

---

## 4. Clonar el repositorio vía SSH

```bash
# Conectarse al servidor
ssh usuario@ssh.hostinger.com -p 65002

# Ir al directorio public_html
cd ~/public_html

# Clonar el repositorio (usa HTTPS si no tienes clave SSH configurada en GitHub)
git clone https://github.com/tu-org/soft_olamtex.git app.tudominio.com

cd app.tudominio.com
```

---

## 5. Configurar PHP y Composer

Verifica la versión de PHP disponible:
```bash
php8.2 -v
```

Si hay múltiples versiones, usa el alias de PHP 8.2 en todos los comandos siguientes.  
En Hostinger el binario suele ser `php8.2` o configurable desde hPanel → **PHP**.

**Instalar dependencias de Composer:**
```bash
php8.2 /usr/local/bin/composer install --no-dev --optimize-autoloader
```

> Si Composer no está disponible:
> ```bash
> curl -sS https://getcomposer.org/installer | php8.2
> php8.2 composer.phar install --no-dev --optimize-autoloader
> ```

---

## 6. Configurar el archivo `.env`

```bash
cp .env.example .env
nano .env
```

Ajusta los valores críticos:

```dotenv
APP_NAME="OlamTex ERP"
APP_ENV=production
APP_KEY=                        # Se genera en el paso siguiente
APP_DEBUG=false
APP_URL=https://app.tudominio.com

# Base de datos (creada desde hPanel → Bases de datos)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_olamtex
DB_USERNAME=u123456789_user
DB_PASSWORD=TuPasswordSeguro

# Caché y sesión (file es lo más seguro para hosting compartido)
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail (configura con tus credenciales SMTP de Hostinger o Brevo)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@tudominio.com
MAIL_PASSWORD=TuPasswordCorreo
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="${APP_NAME}"

# Filesystem
FILESYSTEM_DISK=local
```

Guarda con `Ctrl+O`, sal con `Ctrl+X`.

---

## 7. Generar clave de aplicación

```bash
php8.2 artisan key:generate
```

---

## 8. Permisos de directorios

```bash
chmod -R 775 storage bootstrap/cache
chown -R $(whoami):$(whoami) storage bootstrap/cache
```

---

## 9. Compilar assets frontend (Node.js)

Hostinger Business incluye Node.js. Verifica:
```bash
node -v
npm -v
```

Si Node no está disponible en el servidor, **compila localmente** y sube el build:

**Opción A — Compilar en servidor:**
```bash
npm install
npm run build
```

**Opción B — Subir build compilado localmente (recomendado para hosting compartido):**
```bash
# Desde tu máquina local
npm run build

# Sube la carpeta public/build al servidor
scp -P 65002 -r public/build usuario@ssh.hostinger.com:~/public_html/app.tudominio.com/public/build
```

> El directorio `public/build` generado por Vite contiene todos los assets. No incluyas `node_modules` en el servidor.

---

## 10. Ejecutar migraciones y seeders

```bash
# Solo migraciones (producción, sin seeders destructivos)
php8.2 artisan migrate --force

# Si es primera instalación con datos base
php8.2 artisan migrate --seed --force
```

---

## 11. Optimizar para producción

```bash
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache
php8.2 artisan optimize
```

---

## 12. Configurar el enlace de storage

```bash
php8.2 artisan storage:link
```

Esto crea el enlace simbólico `public/storage → storage/app/public` para servir archivos subidos.

---

## 13. Verificar el archivo `.htaccess` en `public/`

Laravel incluye un `.htaccess` optimizado. Confirma que existe:
```bash
cat public/.htaccess
```

Si Hostinger requiere ajuste para URLs limpias, asegúrate que contenga:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 14. Configurar HTTPS / SSL

1. En hPanel: **SSL → Gestionar**.
2. Emite certificado gratuito (Let's Encrypt) para `app.tudominio.com`.
3. Activa **Forzar HTTPS**.

En `.env` confirma:
```dotenv
APP_URL=https://app.tudominio.com
```

Si hay problemas con URLs tras proxy:
```php
// bootstrap/app.php o AppServiceProvider
\Illuminate\Support\Facades\URL::forceScheme('https');
```

---

## 15. Proceso de actualización (deploys siguientes)

```bash
ssh usuario@ssh.hostinger.com -p 65002
cd ~/public_html/app.tudominio.com

# 1. Bajar código nuevo
git pull origin main

# 2. Actualizar dependencias PHP (si cambiaron)
php8.2 /usr/local/bin/composer install --no-dev --optimize-autoloader

# 3. Subir nuevo build de assets (desde local si no hay Node en servidor)
# scp -P 65002 -r public/build usuario@servidor:~/public_html/app.tudominio.com/public/build

# 4. Ejecutar migraciones pendientes
php8.2 artisan migrate --force

# 5. Limpiar y regenerar cachés
php8.2 artisan optimize:clear
php8.2 artisan optimize
```

---

## 16. Configuración de colas (Queue)

Si el proyecto usa `QUEUE_CONNECTION=database`:
```bash
# Crear las tablas de cola (si no existen)
php8.2 artisan queue:table
php8.2 artisan migrate --force
```

Para procesar colas automáticamente en Hostinger usa **Cron Jobs**:

1. hPanel → **Avanzado → Tareas Cron**.
2. Agrega:
   - Frecuencia: `* * * * *` (cada minuto)
   - Comando:
     ```
     /usr/local/bin/php8.2 /home/usuario/public_html/app.tudominio.com/artisan schedule:run >> /dev/null 2>&1
     ```

---

## 17. Verificación final

```bash
# Verificar que la aplicación responde
curl -I https://app.tudominio.com

# Verificar logs si hay errores
tail -f storage/logs/laravel.log
```

Accede desde el navegador a `https://app.tudominio.com` y confirma que carga correctamente.

---

## Resumen de comandos en orden

```bash
ssh usuario@ssh.hostinger.com -p 65002
cd ~/public_html
git clone https://github.com/tu-org/soft_olamtex.git app.tudominio.com
cd app.tudominio.com
php8.2 /usr/local/bin/composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env                        # editar variables de entorno
php8.2 artisan key:generate
chmod -R 775 storage bootstrap/cache
npm install && npm run build     # o subir build desde local
php8.2 artisan migrate --seed --force
php8.2 artisan storage:link
php8.2 artisan optimize
```

---

## Solución de problemas comunes

| Problema | Solución |
|---|---|
| Error 500 al entrar | Revisar `storage/logs/laravel.log` y permisos de `storage/` |
| CSS/JS no carga | Confirmar que `public/build/` existe y `APP_URL` es correcto |
| Database connection refused | Verificar credenciales DB en `.env` y que el host sea `127.0.0.1` |
| `php artisan` no ejecuta | Usar `php8.2 artisan` en lugar de `php artisan` |
| Imágenes de storage no cargan | Ejecutar `php8.2 artisan storage:link` nuevamente |
| Redirects infinitos HTTPS | Agregar `URL::forceScheme('https')` en `AppServiceProvider` |
| Composer: `allow-plugins` error | Agregar `--no-plugins` o aceptar plugins interactivamente |
