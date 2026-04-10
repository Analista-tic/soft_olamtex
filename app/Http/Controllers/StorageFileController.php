<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageFileController extends Controller
{
    private const ALLOWED_PREFIXES = [
        'avatars/',
        'items/',
        'company-logos/',
        'product-sheets/',
        'product_sheets/',
        'purchase-invoices/',
        'purchase-requests/',
        'purchase_requests/',
        'supply-requests/',
        'inventory_requests/',
        'articles/',
    ];

    /**
     * Servir un archivo del disco public sin depender del symlink public/storage.
     * Permite que las imágenes y documentos carguen en entornos donde el symlink
     * no está disponible (ej. Hostinger shared hosting).
     */
    public function show(string $path): StreamedResponse
    {
        $normalizedPath = ltrim($path, '/');

        if (str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $allowed = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $absolutePath = $disk->path($normalizedPath);

        if (!is_file($absolutePath)) {
            abort(404);
        }

        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            abort(404);
        }

        $detectedMime = mime_content_type($absolutePath);
        $mimeType = is_string($detectedMime) && $detectedMime !== '' ? $detectedMime : 'application/octet-stream';
        $size = filesize($absolutePath);
        $contentLength = is_int($size) ? (string) $size : '0';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'  => $mimeType,
            'Content-Length' => $contentLength,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
