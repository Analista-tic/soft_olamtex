{{-- filepath: c:\Users\ANALISTA\Desktop\Proyectos web\soft_olamtex\resources\views\pdf\purchase_order.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra #{{ $order->id }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fff;
            color: #222;
            margin: 0;
            padding: 0;
            width: 210mm;
            min-height: 297mm;
        }
        .container {
            padding: 24px 36px;
            min-height: 277mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .grid { display: grid; gap: 16px; }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .mb-4 { margin-bottom: 16px; }
        .font-bold { font-weight: bold; }
        .font-semibold { font-weight: 600; }
        .text-xs { font-size: 12px; }
        .text-sm { font-size: 14px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .rounded { border-radius: 8px; }
        .border { border: 1px solid #d1d5db; }
        .border-t { border-top: 1px solid #aaa; }
        .block { display: block; }
        .w-48 { width: 192px; }
        .mt-1 { margin-top: 4px; }
        .mt-12 { margin-top: 48px; }
        .p-4 { padding: 16px; }
        .ps-0 { padding-left: 0; }
        .pe-10 { padding-right: 40px; }
        .items-end { align-items: flex-end; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: 4px; }
        .gap-4 { gap: 16px; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .min-w-120 { min-width: 120px; }
        .mb-2 { margin-bottom: 8px; }
        .mt-2 { margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 8px; }
        th { background: #f3f4f6; }
        img { max-height: 48px; object-fit: contain; }
    </style>
</head>
<body>
    <div class="container">
        <div>
            <div class="grid grid-cols-3 items-start gap-4 mb-4">
                <div>
                    <div class="font-bold">N° Orden de Compra</div>
                    <div>{{ $order->id }}</div>
                </div>
                <div class="flex h-full items-center justify-center rounded p-4">
                    <img src="{{ public_path('images/olamtex.png') }}" alt="Logo Olamtex" />
                </div>
                <div>
                    <div class="text-sm font-semibold">Datos empresa</div>
                    <p class="text-xs">Razón Social: <span class="font-normal">MANUFACTURAS OLAMTEX S.A.S.</span></p>
                    <p class="text-xs">NIT: <span class="font-normal">900123456-7</span></p>
                    <p class="text-xs">Correo: <span class="font-normal">info@olamtex.com</span></p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <div class="font-bold">Proveedor</div>
                    <div>{{ $order->contact->full_name ?? '' }}</div>
                </div>
                <div>
                    <div class="font-bold">Identificación</div>
                    <div>{{ $order->contact->id_number ?? '' }}</div>
                </div>
                <div>
                    <div class="font-bold">Teléfono</div>
                    <div>{{ $order->contact->phone ?? '' }}</div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <div class="font-bold">Fecha de creación</div>
                    <div>{{ $order->date }}</div>
                </div>
                <div>
                    <div class="font-bold">Fecha de entrega</div>
                    <div>{{ $order->delivery_date }}</div>
                </div>
                <div>
                    <div class="font-bold">Número de orden de compra del proveedor</div>
                    <div>{{ $order->supplier_order_number }}</div>
                </div>
            </div>
            <div class="mb-4">
                <div class="font-bold mb-2">Items</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $idx => $item)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $item->article->name ?? '' }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">
                                {{ number_format($item->price, 0, ',', '.') }}
                            </td>
                            <td class="text-center">{{ round($item->tax) }} %</td>
                            <td class="text-right">
                                {{ number_format($item->total, 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @php
            // Subtotal: suma de (precio * cantidad) de cada ítem
            $subtotal = collect($order->items)->reduce(function($acc, $item) {
                return $acc + ((float)$item->price * (float)$item->quantity);
            }, 0);
            // IVA total: suma de ((precio * cantidad) * (tax/100)) de cada ítem
            $iva = collect($order->items)->reduce(function($acc, $item) {
                return $acc + (((float)$item->price * (float)$item->quantity * ((float)$item->tax ?: 0)) / 100);
            }, 0);
            // Total: subtotal + iva
            $total = $subtotal + $iva;
        @endphp
        @if (!function_exists('money_col'))
            @php
                function money_col($value) {
                    return '$' . number_format($value, 0, ',', '.');
                }
            @endphp
        @endif
        <div class="flex flex-col items-end space-y-0 text-sm mb-4 mt-2">
            <div class="flex gap-1">
                <div class="flex flex-col items-end">
                    <span class="font-semibold">Subtotal:</span>
                    <span class="font-semibold">IVA total:</span>
                    <span class="font-semibold">Total orden de compra:</span>
                </div>
                <div class="flex min-w-120 flex-col items-end text-right pe-10">
                    <span>{{ money_col($subtotal) }}</span>
                    <span>{{ money_col($iva) }}</span>
                    <span class="font-bold">{{ money_col($total) }}</span>
                </div>
            </div>
        </div>
        <div>
            <div class="font-bold">Observaciones</div>
            <div>{{ $order->description }}</div>
        </div>
        <div class="flex justify-between items-end mt-12">
            <div class="flex flex-col items-center">
                <span class="border-t border-gray-400 w-48 block"></span>
                <span class="text-xs mt-1">Firma Autorizada</span>
            </div>
            <div class="flex flex-col items-center">
                <span class="border-t border-gray-400 w-48 block"></span>
                <span class="text-xs mt-1">Firma Proveedor</span>
            </div>
        </div>
    </div>
</body>
</html>
