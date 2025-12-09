<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Vault\VaultEntry;
use App\Models\Vault\VaultSubKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VaultSubKeyController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vault_entry_id' => 'required|exists:vault_entries,id',
            'encrypted_payload' => 'required|string',
            'iv' => 'required|string|size:32',
            'salt' => 'required|string|size:64',
        ]);

        // Verify the vault entry belongs to the authenticated user
        $vaultEntry = VaultEntry::findOrFail($request->vault_entry_id);
        if ($vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $subKey = VaultSubKey::create([
            'vault_entry_id' => $request->vault_entry_id,
            'encrypted_payload' => $request->encrypted_payload,
            'iv' => $request->iv,
            'salt' => $request->salt,
        ]);

        return response()->json($subKey, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(VaultSubKey $vaultSubKey)
    {
        // Ensure the user owns this sub-key through the parent vault entry
        if ($vaultSubKey->vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json($vaultSubKey);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VaultSubKey $vaultSubKey)
    {
        // Ensure the user owns this sub-key through the parent vault entry
        if ($vaultSubKey->vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'encrypted_payload' => 'required|string',
            'iv' => 'required|string|size:32',
            'salt' => 'required|string|size:64',
        ]);

        $vaultSubKey->update([
            'encrypted_payload' => $request->encrypted_payload,
            'iv' => $request->iv,
            'salt' => $request->salt,
        ]);

        return response()->json($vaultSubKey);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VaultSubKey $vaultSubKey)
    {
        // Ensure the user owns this sub-key through the parent vault entry
        if ($vaultSubKey->vaultEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $vaultSubKey->delete();

        return response()->json(null, 204);
    }
}
