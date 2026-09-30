<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita registros repetidos por doble clic o reintento: si el mismo usuario envía una alta (`POST` a una ruta
 * `*.store` sin parámetros) idéntica a otra que se está procesando o que acaba de guardarse, la segunda se rechaza
 * con 409 en vez de crear otro registro. Si la primera falla (validación, error), el bloqueo se libera y se puede
 * reintentar. La garantía final de unicidad sigue siendo la de los índices únicos y los consecutivos atómicos.
 */
class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldGuard($request)) {
            return $next($request);
        }

        $key = 'dup-submit:' . sha1(implode('|', [
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
            $request->path(),
            json_encode($this->fingerprint($request->all())),
        ]));

        if (! Cache::add($key, 1, (int) config('duplicate_submission.processing_seconds', 60))) {
            $message = 'Este registro ya se envió. Espera un momento y revisa la lista antes de volver a intentarlo.';

            return response()->json(['message' => $message, 'errors' => ['general' => [$message]]], 409);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($key);
            throw $e;
        }

        if ($response->getStatusCode() >= 400) {
            Cache::forget($key); // el usuario corrige y reintenta
        } else {
            Cache::put($key, 1, (int) config('duplicate_submission.window_seconds', 4));
        }

        return $response;
    }

    private function shouldGuard(Request $request): bool
    {
        if (! config('duplicate_submission.enabled', true) || $request->method() !== 'POST') {
            return false;
        }

        if (app()->runningUnitTests() && ! config('duplicate_submission.in_tests', false)) {
            return false;
        }

        $route = $request->route();

        return $route !== null
            && str_ends_with((string) $route->getName(), '.store')
            && $route->parameterNames() === [];
    }

    /** Sustituye los archivos por nombre y tamaño para poder comparar el contenido de la petición. */
    private function fingerprint(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return $value->getClientOriginalName() . ':' . $value->getSize();
        }

        return is_array($value) ? array_map(fn ($v) => $this->fingerprint($v), $value) : $value;
    }
}
