<?php
// app/Models/Settings/CompanySetting.php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class CompanySetting extends Model implements \App\Contracts\HasAuditLabel
{
    protected $fillable = [
        'name',
        'nit',
        'phone',
        'email',
        'accounting_email',
        'website',
        'address',
        'municipality',
        'department',
        'description',
        'logo_path',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        $path = ltrim((string) $this->logo_path, '/');
        $path = preg_replace('/^storage\//', '', $path);

        return route('storage.file.show', ['path' => $path]);
    }

    public static function auditLabel(): string
    {
        return 'Configuración de empresa';
    }
}