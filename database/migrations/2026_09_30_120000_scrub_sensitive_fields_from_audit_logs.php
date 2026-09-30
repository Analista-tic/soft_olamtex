<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina de la auditoría existente los campos sensibles (hash de contraseña,
 * remember_token, secretos 2FA) que se guardaron antes de excluirlos.
 * Se usa query builder porque el modelo AuditLog es de solo inserción.
 */
return new class extends Migration
{
    private const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $keysPattern = implode('|', self::SENSITIVE_KEYS);

        DB::table('audit_logs')
            ->where(function ($query) {
                foreach (self::SENSITIVE_KEYS as $key) {
                    $query->orWhere('old_values', 'like', '%"' . $key . '"%')
                        ->orWhere('new_values', 'like', '%"' . $key . '"%');
                }
            })
            ->orderBy('id')
            ->chunkById(500, function ($logs) use ($keysPattern) {
                foreach ($logs as $log) {
                    $description = $log->description;

                    if ($description !== null) {
                        // Actualizaciones: "Campo 'password' cambiado de '...' a '...'"
                        $field = "Campo '(?:{$keysPattern})' cambiado de '[^']*' a '[^']*'";
                        $description = preg_replace("/{$field}; /u", '', $description);
                        $description = preg_replace("/; {$field}(?=\.$)/u", '', (string) $description);
                        $description = preg_replace("/:\s*{$field}\.$/u", '.', (string) $description);

                        // Creación/eliminación: "password: valor; ..."
                        $pair = "\b(?:{$keysPattern}): [^;]*";
                        $description = preg_replace("/{$pair}; /u", '', (string) $description);
                        $description = preg_replace("/; {$pair}(?=\.$)/u", '', (string) $description);
                        $description = preg_replace("/Valores: {$pair}\.$/u", 'Valores: sin valores.', (string) $description);
                    }

                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'old_values' => $this->scrub($log->old_values),
                        'new_values' => $this->scrub($log->new_values),
                        'description' => $description,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: los valores sensibles no deben restaurarse.
    }

    private function scrub(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $values = json_decode($json, true);

        if (!is_array($values)) {
            return $json;
        }

        $values = array_diff_key($values, array_flip(self::SENSITIVE_KEYS));

        return $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE);
    }
};
