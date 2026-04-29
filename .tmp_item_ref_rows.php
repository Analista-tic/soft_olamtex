<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('items')
    ->select('id', 'reference', 'deleted_at')
    ->where('reference', 'TEST-REF-001')
    ->orderBy('id')
    ->get();

foreach ($rows as $row) {
    echo 'ID: ' . $row->id . ' | REF: ' . $row->reference . ' | deleted_at: ' . ($row->deleted_at ?? 'NULL') . PHP_EOL;
}

echo 'TOTAL: ' . count($rows) . PHP_EOL;
