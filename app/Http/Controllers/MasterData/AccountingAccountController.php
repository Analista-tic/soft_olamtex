<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingAccountController extends Controller
{
    /**
     * Devuelve opciones de cuentas contables para formularios (filtro por tipo y si permite movimientos).
     */
    public function options(Request $request)
    {
        $types = $request->input('account_type');
        $allowsEntries = $request->boolean('allows_entries', false);

        $query = DB::table('accounting_accounts');
        if ($types) {
            $typesArr = is_array($types) ? $types : explode(',', $types);
            $query->whereIn('type', $typesArr);
        }
        if ($allowsEntries) {
            $query->where('allows_entries', true);
        }
        $accounts = $query->select('id', 'code', 'name', 'type')->orderBy('code')->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }
}
