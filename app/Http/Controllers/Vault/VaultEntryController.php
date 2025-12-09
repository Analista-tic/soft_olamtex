<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Vault\VaultEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VaultEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Vault/Index');
    }

    /**
     * Get all vault entries for the authenticated user.
     */
    public function getData()
    {
        $entries = VaultEntry::where('user_id', Auth::id())
            ->with('subKeys')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'encrypted_payload' => 'required|string',
            'iv' => 'required|string|size:32',
            'salt' => 'required|string|size:64',
        ]);

        $entry = VaultEntry::create([
            'user_id' => Auth::id(),
            'encrypted_payload' => $request->encrypted_payload,
            'iv' => $request->iv,
            'salt' => $request->salt,
        ]);

        return response()->json($entry, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(VaultEntry $vaultEntry)
    {
        // Ensure the user owns this entry
        if ($vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $vaultEntry->load('subKeys');

        return response()->json($vaultEntry);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VaultEntry $vaultEntry)
    {
        // Ensure the user owns this entry
        if ($vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'encrypted_payload' => 'required|string',
            'iv' => 'required|string|size:32',
            'salt' => 'required|string|size:64',
        ]);

        $vaultEntry->update([
            'encrypted_payload' => $request->encrypted_payload,
            'iv' => $request->iv,
            'salt' => $request->salt,
        ]);

        return response()->json($vaultEntry);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VaultEntry $vaultEntry)
    {
        // Ensure the user owns this entry
        if ($vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $vaultEntry->delete();

        return response()->json(null, 204);
    }
}
