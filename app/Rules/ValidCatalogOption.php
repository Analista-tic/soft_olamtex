<?php

namespace App\Rules;

use App\Services\Settings\SettingService;
use Illuminate\Contracts\Validation\Rule;

class ValidCatalogOption implements Rule
{
    public function __construct(
        private SettingService $settingService,
        private string $catalogKey,
        private string $catalogLabel,
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if ($value === null) {
            return true;
        }

        $catalog = $this->settingService->getCatalogOptions($this->catalogKey);
        if ($catalog->isEmpty()) {
            return false;
        }

        return $catalog->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->contains((string) $value);
    }

    public function message(): string
    {
        return "El valor seleccionado no es válido para {$this->catalogLabel}.";
    }
}
