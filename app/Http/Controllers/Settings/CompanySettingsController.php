<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\CompanySettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Settings\CompanySetting;

class CompanySettingsController extends Controller
{
    public function __construct(protected CompanySettingsService $service) {}
    /**
     * Show the basic data form
     */
    public function edit(Request $request)
    {
        $company = $this->service->get();
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

        $logo = $request->file('logo');

        $result = $this->service->update($data, $logo);

        $message = $result['saved'] ? 'Los datos básicos se actualizaron correctamente.' : 'No se detectaron cambios.';

        return response()->json([
            'company' => $result['company'],
            'message' => $message,
        ]);
    }
}
