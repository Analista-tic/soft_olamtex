<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\CatalogSyncEvent;
use Illuminate\Http\Request;

class CatalogSyncController extends Controller
{
    // Límite defensivo: evita que un cliente mal formado pida miles de claves.
    private const MAX_KEYS = 50;

    /**
     * Devuelve, para cada clave de catálogo pedida, la última vez que cambió (o null
     * si nunca se ha tocado). El frontend compara contra lo último que vio y decide
     * si refrescar — ver resources/js/lib/crossTabSync.ts.
     */
    public function index(Request $request)
    {
        $keys = collect(explode(',', (string) $request->query('keys', '')))
            ->map(fn ($key) => trim($key))
            ->filter()
            ->unique()
            ->take(self::MAX_KEYS)
            ->values();

        if ($keys->isEmpty()) {
            return response()->json([]);
        }

        $timestamps = CatalogSyncEvent::query()
            ->whereIn('catalog_key', $keys)
            ->pluck('updated_at', 'catalog_key');

        $result = $keys->mapWithKeys(fn ($key) => [
            $key => $timestamps->get($key)?->toISOString(),
        ]);

        return response()->json($result);
    }

    /**
     * Marca que un catálogo cambió ahora mismo. Se llama junto a la invalidación local
     * (broadcastCatalogInvalidated) cada vez que se crea/edita un registro del catálogo.
     */
    public function touch(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
        ]);

        CatalogSyncEvent::query()->updateOrInsert(
            ['catalog_key' => $validated['key']],
            ['updated_at' => now()],
        );

        return response()->noContent();
    }
}
