# Script para desarrollo RÁPIDO sin queue listener
# Úsalo si solo estás haciendo cambios frontend/backend sin necesidad de jobs

$ErrorActionPreference = 'Stop'

Write-Host "⚡ Iniciando desarrollo RÁPIDO (sin queue listener)..." -ForegroundColor Cyan

# 1. Limpiar caché previo
Write-Host "`n🧹 Limpiando caché..." -ForegroundColor Yellow
php artisan cache:clear 2>$null | Out-Null
php artisan config:cache 2>$null | Out-Null

# 2. Iniciar servidor Laravel
Write-Host "`n📦 Iniciando servidor Laravel en http://127.0.0.1:8000" -ForegroundColor Green
$laravelProcess = Start-Process -FilePath "php" -ArgumentList "artisan", "serve" -NoNewWindow -PassThru

Start-Sleep -Seconds 2

# 3. Iniciar compilador frontend
Write-Host "`n📚 Iniciando compilador frontend..." -ForegroundColor Green
$npmProcess = Start-Process -FilePath "npm" -ArgumentList "run", "dev" -NoNewWindow -PassThru

Write-Host "`n✅ Servicios iniciados!`n" -ForegroundColor green
Write-Host "Laravel:  http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Presiona Ctrl+C para detener`n" -ForegroundColor Yellow

# Esperar a que el usuario detenga
try {
    Get-Process php | Wait-Process
} catch {
    # Usuario presionó Ctrl+C
}

# Limpiar
Write-Host "`n🛑 Deteniendo procesos..." -ForegroundColor Yellow
$npmProcess | Stop-Process -Force -ErrorAction SilentlyContinue
$laravelProcess | Stop-Process -Force -ErrorAction SilentlyContinue

Write-Host "✅ Desarrollo detenido" -ForegroundColor Green
