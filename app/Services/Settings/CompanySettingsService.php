<?php

namespace App\Services\Settings;

use App\Models\Settings\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanySettingsService
{
    private const CACHE_KEY = 'company_setting';

    /**
     * Se lee en cada navegación Inertia (ver HandleInertiaRequests::share()), así que
     * se cachea sin expiración: cambia únicamente cuando update() lo guarda, momento
     * en que se invalida explícitamente.
     */
    public function get(): ?CompanySetting
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => CompanySetting::first());
    }

    public function update(array $data, ?UploadedFile $logo = null): array
    {
        $company = CompanySetting::firstOrNew(['id' => 1]);

        if ($logo) {
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $company->logo_path = $logo->store('company-logos', 'public');
        }

        $company->fill($data);

        $saved = false;
        if ($company->isDirty() || $logo) {
            $company->save();
            $saved = true;
        }

        if ($saved) {
            Cache::forget(self::CACHE_KEY);
        }

        return [
            'company' => $company->fresh(),
            'saved' => $saved,
        ];
    }
}
