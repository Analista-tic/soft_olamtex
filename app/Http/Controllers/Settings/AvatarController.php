<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    private const MAX_AVATAR_BYTES = 5 * 1024 * 1024;

    /**
     * Subir o cambiar el avatar del usuario autenticado.
     */
    public function upload(AvatarUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('avatar');

        $this->deleteOldAvatar($user->avatar_path);

        $path = $file->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);
        $user->refresh();

        return response()->json([
            'message' => 'Avatar actualizado correctamente.',
            'avatar_path' => $path,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Guardar avatar recortado (crop).
     * Recibe la imagen ya recortada en base64.
     */
    public function crop(Request $request): JsonResponse
    {
        $request->validate([
            'cropped_image' => 'required|string',
        ]);

        $user = $request->user();
        $base64Image = $request->input('cropped_image');

        $imageData = $this->decodeBase64Image($base64Image);

        if (!$imageData) {
            return response()->json([
                'message' => 'Imagen invalida.',
            ], 422);
        }

        if (strlen($imageData['data']) > self::MAX_AVATAR_BYTES) {
            return response()->json([
                'message' => 'La imagen no debe superar los 5MB.',
            ], 422);
        }

        if (@getimagesizefromstring($imageData['data']) === false) {
            return response()->json([
                'message' => 'Imagen invalida.',
            ], 422);
        }

        $this->deleteOldAvatar($user->avatar_path);

        $filename = 'avatar_' . $user->id . '_' . Str::random(10) . '.' . $imageData['extension'];
        $path = 'avatars/' . $filename;

        Storage::disk('public')->put($path, $imageData['data']);

        $user->update(['avatar_path' => $path]);
        $user->refresh();

        return response()->json([
            'message' => 'Avatar ajustado correctamente.',
            'avatar_path' => $path,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Eliminar el avatar del usuario.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->deleteOldAvatar($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return response()->json([
            'message' => 'Avatar eliminado correctamente.',
            'avatar_path' => null,
            'avatar_url' => null,
        ]);
    }

    /**
     * Servir avatar desde el disk public (fallback cuando /storage no es accesible por servidor web).
     */
    public function show(string $path): StreamedResponse
    {
        $normalizedPath = ltrim($path, '/');

        if (!str_starts_with($normalizedPath, 'avatars/')) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($normalizedPath)) {
            abort(404);
        }

        return Storage::disk('public')->response($normalizedPath, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Eliminar avatar anterior del disco si existe.
     */
    private function deleteOldAvatar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Decodificar imagen base64.
     *
     * @return array{data: string, extension: string}|null
     */
    private function decodeBase64Image(string $base64Image): ?array
    {
        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $base64Image, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $data = base64_decode($data, true);

        if ($data === false || $data === '') {
            return null;
        }

        return [
            'data' => $data,
            'extension' => $extension,
        ];
    }
}
