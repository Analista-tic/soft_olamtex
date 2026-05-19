# Script para optimizar el arranque de la aplicación
# Ejecuta esto una sola vez o cuando cambies la configuración

Write-Host "🚀 Optimizando startup de soft_olamtex..." -ForegroundColor Cyan

Write-Host "`n1/5 Limpiando caché de configuración..." -ForegroundColor Yellow
php artisan config:clear
php artisan config:cache

Write-Host "`n2/5 Cacheando rutas..." -ForegroundColor Yellow
php artisan route:cache

Write-Host "`n3/5 Cacheando vistas..." -ForegroundColor Yellow
php artisan view:cache

Write-Host "`n4/5 Esperando que las migraciones estén al día..." -ForegroundColor Yellow
php artisan migrate --force 2>$null

Write-Host "`n5/5 Limpiar caché de aplicación..." -ForegroundColor Yellow
php artisan cache:clear

Write-Host "`n✅ Optimización completada!" -ForegroundColor Green
Write-Host "`nAhora ejecuta: composer run dev" -ForegroundColor Cyan
