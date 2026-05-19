#!/usr/bin/env php
<?php
/**
 * Script: composer run dev:fast
 * 
 * Ejecuta solo lo necesario para desarrollo frontend/backend sin queue listener.
 * Aproximadamente 5-7 segundos más rápido que 'composer run dev'
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$processes = [];

echo "\n🚀 Iniciando desarrollo rápido (sin queue listener)...\n\n";

// 1. Laravel Server
echo "▶️  Iniciando Laravel Server...\n";
$laravelProcess = new Process(['php', 'artisan', 'serve']);
$laravelProcess->start();
$processes[] = $laravelProcess;

// Esperar un segundo para que Laravel se prepare
sleep(2);

// 2. Vite Dev Server
echo "▶️  Iniciando Vite Dev Server...\n";
$viteProcess = new Process(['npm', 'run', 'dev']);
$viteProcess->start();
$processes[] = $viteProcess;

echo "\n✅ Servicios iniciados!\n";
echo "   Laravel: http://127.0.0.1:8000\n";
echo "   Presiona Ctrl+C para detener\n\n";

// Mantener procesos corriendo
foreach ($processes as $process) {
    $process->wait();
}

echo "\n🛑 Desarrollo detenido\n";
