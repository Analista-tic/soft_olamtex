<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanySettingRequest;
use App\Models\Settings\CompanySetting;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompanySettingsController extends Controller
{
    public function edit()
    {
        $company = CompanySetting::first();

        return Inertia::render('Configuration/BasicData/Index', [
            'company' => $company,
        ]);
    }

    public function update(CompanySettingRequest $request)
    {
        $company = CompanySetting::first() ?? new CompanySetting();
        $logo = $request->file('logo');

        if ($logo) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = $logo->store('company/logos', 'public');
        }

        $company->fill($request->safe()->except('logo')->toArray());
        $company->save();

        return response()->json([
            'message' => 'Datos básicos actualizados correctamente.',
            'company' => $company->refresh(),
        ]);
    }
}
