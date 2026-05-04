<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = App\Models\MasterData\Item::find(1068);
if (!$item) {
    echo "NOT FOUND\n";
    exit(0);
}

echo "ID: " . $item->id . " | REF: " . $item->reference . "\n";

$rule = Illuminate\Validation\Rule::unique('items', 'reference')->ignore($item->id);
$validator = Illuminate\Support\Facades\Validator::make(
    ['reference' => 'TEST-REF-001'],
    ['reference' => [$rule]]
);

echo $validator->fails()
    ? "FALLA: " . $validator->errors()->first()
    : "PASA OK";
