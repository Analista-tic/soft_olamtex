<?php
/**
 * Script de diagnóstico para MUT-0006
 * Ejecutar: php diagnostico_mut0006.php
 */

require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->handle($request);

use App\Models\Production\ProductionOrder;
use App\Models\ProductSheet\Programmer;
use App\Models\Production\TechnicalSheet;

echo "=== DIAGNÓSTICO MUT-0006 ===\n\n";

// 1. ¿Existe la OP?
$op = ProductionOrder::where('op_code', 'MUT-0006')->first();
echo "1. OP MUT-0006 existe: " . ($op ? "✓ SÍ (ID: {$op->id})" : "✗ NO") . "\n";

if (!$op) {
    echo "\n❌ ERROR: La OP MUT-0006 no existe en la BD.\n";
    exit(1);
}

echo "   - Product Sheet ID: {$op->product_sheet_id}\n";
echo "   - Status: {$op->status}\n\n";

// 2. ¿Existe TechnicalSheet?
$sheet = TechnicalSheet::where('production_order_id', $op->id)->first();
echo "2. TechnicalSheet para OP {$op->id}: " . ($sheet ? "✓ SÍ (ID: {$sheet->id})" : "✗ NO") . "\n";

if (!$sheet) {
    echo "   ⚠️ PROBLEMA ENCONTRADO: No hay TechnicalSheet asociada a esta OP.\n";
    echo "   Esto causa: processesSummary, operationalListDetail, index (insumos) fallan.\n\n";
} else {
    echo "   - Status: {$sheet->status}\n\n";
}

// 3. ¿Tiene ProductOperationList?
if ($op->product_sheet_id) {
    $productSheet = $op->productSheet;
    echo "3. Product Sheet {$op->product_sheet_id}: ";
    if ($productSheet) {
        echo "✓ SÍ\n";
        echo "   - Operational List ID: {$productSheet->operational_list_id}\n";
        echo "   - Type: {$productSheet->type}\n\n";
    } else {
        echo "✗ NO (Product Sheet no existe!)\n\n";
    }
}

// 4. ¿Hay operaciones (TechnicalSheetOperation)?
$operations = \App\Models\Production\TechnicalSheetOperation::where('technical_sheet_id', $sheet?->id ?? 0)->count();
echo "4. Operaciones técnicas: " . ($operations > 0 ? "✓ {$operations} encontradas" : "✗ NINGUNA") . "\n\n";

echo "=== RESUMEN ===\n";
if ($sheet && $operations > 0) {
    echo "✓ TODO OK: La OP tiene TechnicalSheet y operaciones.\n";
    echo "   El error puede ser en lógica de cálculo.\n";
} elseif ($sheet && $operations === 0) {
    echo "⚠️ ADVERTENCIA: TechnicalSheet existe pero sin operaciones.\n";
    echo "   Los endpoints funcionarán pero con datos vacíos.\n";
} else {
    echo "❌ CRÍTICO: Falta TechnicalSheet. DEBE CREARSE.\n";
}
