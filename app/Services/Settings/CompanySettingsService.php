<?php

namespace App\Services\Settings;

use App\Models\Settings\CompanySetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class CompanySettingsService
{
    public function get(): ?CompanySetting
    {
        return CompanySetting::first();
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

        return [
            'company' => $company->fresh(),
            'saved' => $saved,
        ];
    }
}
