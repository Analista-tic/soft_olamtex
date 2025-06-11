<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Articles/Index', [
            'articles' => Article::with(['warehouse', 'categorySubGroup', 'color'])->get(),
        ]);
    }

    public function show($id)
    {
        return Article::findOrFail($id);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'warehouse_id' => 'required|integer',
                'category_sub_group_id' => 'required|integer',
                'color_id' => 'required|integer',
                'img' => 'nullable|string',
                'impuesto' => 'required|numeric',
                'stock_min' => 'required|integer',
                'stock_max' => 'required|integer',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'required',
                'description' => 'required|string',
                'extra_fields' => 'nullable',
                'reference' => 'required|string|unique:articles,reference', // Cambia a required
                'status' => 'sometimes|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar la solicitud.',
                'errors' => [],
            ], 500);
        }

        // Procesar imagen si existe
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('articles', 'public');
            // Guardar solo el path relativo, NO Storage::url($path)
            $data['img'] = $path;
        }

        // Procesar extra_fields como array asociativo
        $extraFields = [];
        if (!empty($data['extra_fields'])) {
            $extraFields = is_array($data['extra_fields'])
                ? $data['extra_fields']
                : (json_decode($data['extra_fields'], true) ?: []);
        }
        $data['extra_fields'] = $extraFields;

        // Asegurar que negative_movement sea booleano
        $data['negative_movement'] = filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        // Generar el campo name
        $subGroupName = optional(\App\Models\Common\CategorySubGroup::find($data['category_sub_group_id']))->name ?? '';
        $colorName = optional(\App\Models\Common\Color::find($data['color_id']))->name ?? '';
        $extrasString = implode(' ', array_filter(array_map('trim', array_values($extraFields))));
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        // No generar referencia automáticamente, solo guardar la del frontend

        if (!isset($data['status'])) {
            $data['status'] = true;
        }

        $article = Article::create($data);

        return $article;
    }

    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        // Decodificar extra_fields si viene como string
        if ($request->has('extra_fields') && is_string($request->input('extra_fields'))) {
            $decoded = json_decode($request->input('extra_fields'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['extra_fields' => $decoded]);
            }
        }

        try {
            $data = $request->validate([
                'warehouse_id' => 'sometimes|integer',
                'category_sub_group_id' => 'sometimes|integer',
                'color_id' => 'sometimes|integer',
                'img' => 'nullable|string',
                'impuesto' => 'sometimes|numeric',
                'stock_min' => 'sometimes|integer',
                'stock_max' => 'sometimes|integer',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'sometimes',
                'description' => 'sometimes|string',
                'extra_fields' => 'nullable',
                'reference' => 'required|string|unique:articles,reference,' . $article->id, // Cambia a required
                'status' => 'sometimes|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar la solicitud.',
                'errors' => [],
            ], 500);
        }

        // Procesar imagen si existe
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('articles', 'public');
            // Guardar solo el path relativo, NO Storage::url($path)
            $data['img'] = $path;
        }

        // Procesar extra_fields como array asociativo
        $extraFields = [];
        if (array_key_exists('extra_fields', $data) && !empty($data['extra_fields'])) {
            $extraFields = is_array($data['extra_fields'])
                ? $data['extra_fields']
                : (json_decode($data['extra_fields'], true) ?: []);
        } else {
            $extraFields = $article->extra_fields ?? [];
        }
        $data['extra_fields'] = $extraFields;

        // Asegurar que negative_movement sea booleano
        if (array_key_exists('negative_movement', $data)) {
            $data['negative_movement'] = filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        // Generar el campo name
        $subGroupId = $data['category_sub_group_id'] ?? $article->category_sub_group_id;
        $colorId = $data['color_id'] ?? $article->color_id;
        $subGroupName = optional(\App\Models\Common\CategorySubGroup::find($subGroupId))->name ?? '';
        $colorName = optional(\App\Models\Common\Color::find($colorId))->name ?? '';
        $extrasString = implode(' ', array_filter(array_map('trim', array_values($extraFields))));
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        // No generar referencia automáticamente, solo guardar la del frontend

        $article->update($data);
        return $article;
    }

    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();
        return response()->noContent();
    }

    public function getData(Request $request)
    {
        $query = Article::with(['warehouse', 'categorySubGroup', 'color']);

        if ($request->has('search') && trim($request->search) !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%$search%")
                  ->orWhere('bar_code', 'like', "%$search%")
                  ->orWhereHas('warehouse', function ($w) use ($search) {
                      $w->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('categorySubGroup', function ($sg) use ($search) {
                      $sg->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('color', function ($c) use ($search) {
                      $c->where('name', 'like', "%$search%");
                  });
            });
        }

        // Filtrar por estado si se proporciona
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool)$request->status);
        }

        $perPage = $request->input('per_page', 10);
        $articles = $query->paginate($perPage);

        return response()->json($articles);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);
        $path = $request->file('image')->store('articles', 'public');
        return response()->json([
            'url' => Storage::url($path)
        ]);
    }

    public function apiIndex(Request $request)
    {
        $query = Article::with(['warehouse', 'categorySubGroup', 'color']);

        if ($request->has('search') && trim($request->search) !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%$search%")
                  ->orWhere('bar_code', 'like', "%$search%")
                  ->orWhereHas('warehouse', function ($w) use ($search) {
                      $w->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('categorySubGroup', function ($sg) use ($search) {
                      $sg->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('color', function ($c) use ($search) {
                      $c->where('name', 'like', "%$search%");
                  });
            });
        }

        // Filtrar por estado si se proporciona
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool)$request->status);
        }

        $perPage = $request->input('per_page', 10);
        $articles = $query->paginate($perPage);

        return response()->json($articles);
    }

    // Nuevo método para cambiar el estado
    public function toggleStatus($id)
    {
        $article = Article::findOrFail($id);
        $article->status = !$article->status;
        $article->save();

        return response()->json([
            'status' => $article->status,
            'message' => $article->status ? 'Artículo habilitado' : 'Artículo deshabilitado'
        ]);
    }
}
