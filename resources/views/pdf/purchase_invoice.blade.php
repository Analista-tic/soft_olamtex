<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura de Compra #{{ $invoice->document_number }}</title>
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
        .mt-2 { margin-top: 8px; }
        .p-4 { padding: 16px; }
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
                    <div class="font-bold">N° Factura de Compra</div>
                    <div>{{ $invoice->document_number }}</div>
                    @if($invoice->supplier_invoice_number)
                    <div class="mt-1 text-xs">
                        <span class="font-semibold">N° Factura Proveedor:</span> {{ $invoice->supplier_invoice_number }}
                    </div>
                    @endif
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
                    <div>{{ $invoice->supplier->full_name ?? $invoice->provider_name ?? '' }}</div>
                </div>
                <div>
                    <div class="font-bold">Identificación</div>
                    <div>{{ $invoice->supplier->id_number ?? '' }}</div>
                </div>
                <div>
                    <div class="font-bold">Teléfono</div>
                    <div>{{ $invoice->supplier->phone ?? '' }}</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <div class="font-bold">Fecha factura</div>
                    <div>{{ $invoice->date }}</div>
                </div>
                <div>
                    <div class="font-bold">Fecha de entrega</div>
                    <div>{{ $invoice->delivery_date ?? '-' }}</div>
                </div>
                <div>
                    <div class="font-bold">Estado</div>
                    <div>{{ ucfirst($invoice->status ?? '') }}</div>
                </div>
            </div>

            <div class="mb-4">
                <div class="font-bold mb-2">Ítems</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ítem</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $idx => $item)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $item->item->name ?? $item->item_name ?? '' }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                            <td class="text-center">{{ round($item->tax) }} %</td>
                            <td class="text-right">{{ number_format($item->total, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $subtotal = collect($invoice->items)->sum(fn($i) => (float)$i->price * (float)$i->quantity);
            $iva      = collect($invoice->items)->sum(fn($i) => (float)$i->price * (float)$i->quantity * ((float)$i->tax / 100));
            $total    = $subtotal + $iva;
        @endphp

        <div class="flex flex-col items-end text-sm mb-4 mt-2">
            <div class="flex gap-1">
                <div class="flex flex-col items-end">
                    <span class="font-semibold">Subtotal:</span>
                    <span class="font-semibold">IVA total:</span>
                    <span class="font-semibold">Total factura:</span>
                </div>
                <div class="flex min-w-120 flex-col items-end text-right pe-10">
                    <span>${{ number_format($subtotal, 0, ',', '.') }}</span>
                    <span>${{ number_format($iva, 0, ',', '.') }}</span>
                    <span class="font-bold">${{ number_format($total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        @if($invoice->description)
        <div class="mb-4">
            <div class="font-bold">Observaciones</div>
            <div>{{ $invoice->description }}</div>
        </div>
        @endif

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
