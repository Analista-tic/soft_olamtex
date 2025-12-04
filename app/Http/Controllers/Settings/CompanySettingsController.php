<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Settings\CompanySetting;

class CompanySettingsController extends Controller
{
    /**
     * Show the basic data form
     */
    public function edit(Request $request)
    {
        $company = CompanySetting::first();
        $section = $request->route('section') ?? 'empresa';

        return Inertia::render('Configuration/BasicData/Index', [
            'company' => $company,
            'activeSection' => $section,
        ]);
    }

    /**
     * Update company settings
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:191',
            'nit' => 'nullable|string|max:60',
            'phone' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:191',
            'accounting_email' => 'nullable|email|max:191',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $company = CompanySetting::firstOrNew(['id' => 1]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            // delete previous logo if exists
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $company->logo_path = $file->store('company-logos', 'public');
        }

        $company->fill($data);

        if ($company->isDirty() || $request->hasFile('logo')) {
            $company->save();
            $message = 'Los datos básicos se actualizaron correctamente.';
        } else {
            $message = 'No se detectaron cambios.';
        }

        return response()->json([
            'company' => $company->fresh(),
            'message' => $message,
        ]);
    }
}
