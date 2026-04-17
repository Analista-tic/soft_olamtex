<?php

namespace App\Services\MasterData;

use App\Helpers\NitHelper;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio dedicado a la importación histórica de contactos/terceros desde CSV legacy.
 *
 * NO modifica el esquema de BD.
 * Hace upsert por id_number: inserta nuevos, actualiza existentes y restaura soft-deleted.
 */
class LegacyContactsMigrationService
{
    // ─────────────────────────────────────────────────────
    // Mapeo columnas CSV → campos del modelo Contact
    // ─────────────────────────────────────────────────────

    protected const COLUMN_MAP = [
        'TIPO DE CONTACTO' => 'contact_type',
        'TIPO CONTACTO' => 'contact_type',
        'TIPO DE IDENTIFICACION' => 'id_type',
        'TIPO IDENTIFICACION' => 'id_type',
        'NUMERO DE IDENTIFICACION' => 'id_number',
        'NUMERO IDENTIFICACION' => 'id_number',
        'DIGITO DE VERIFICACION' => 'dv',
        'DV' => 'dv',
        'RAZON SOCIAL NOMBRE COMPLETO' => 'full_name',
        'RAZON SOCIAL  NOMBRE COMPLETO' => 'full_name',
        'NOMBRE COMPLETO' => 'full_name',
        'RAZON SOCIAL' => 'full_name',
        'TIPO DE PERSONA' => 'person_type',
        'TIPO PERSONA' => 'person_type',
        'REGIMEN TRIBUTARIO' => '_tax_regime',
        'CATEGORIA' => '_category',
        'MUNICIPIO' => 'municipality',
        'DEPARTAMENTO' => 'department',
        'CODIGO POSTAL' => '_postal_code',
        'DIRECCION' => 'address',
        'CORREO ELECTRONICO' => 'email',
        'EMAIL' => 'email',
        'TELEFONO PRINCIPAL' => 'phone',
        'TELEFONO' => 'phone',
        'NOMBRE CONTACTO ADICIONAL' => '_add_contact_name',
        'CARGO CONTACTO ADICIONAL' => '_add_contact_role',
        'TELEFONO CONTACTO ADICIONAL' => '_add_contact_phone',
        'CORREO CONTACTO ADICIONAL' => '_add_contact_email',
        'NOMBRE SUCURSAL' => '_branch_name',
        'DIRECCION SUCURSAL' => '_branch_address',
    ];

    protected const EMAIL_DOMAIN = 'migracion.local';

    // Caches en memoria para evitar N+1
    protected array $categoryGroupCache = [];
    protected array $emailOwners        = [];

    // ─────────────────────────────────────────────────────
    // Punto de entrada
    // ─────────────────────────────────────────────────────

    /**
     * @param  array  $csvPaths  Rutas absolutas a los archivos CSV.
     * @param  string $batchId   Identificador único del lote.
     * @return array  Resumen con total_read, inserted, updated, skipped, errors.
     */
    public function importAll(array $csvPaths, string $batchId): array
    {
        $this->warmCaches();

        $summary = ['total_read' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($csvPaths as $csvPath) {
            $sourceName = basename($csvPath);
            Log::info('legacy_contacts_import.start', ['file' => $sourceName, 'batch' => $batchId]);

            $result = $this->importCsv($csvPath, $sourceName, $batchId);

            $summary['total_read'] += $result['total_read'];
            $summary['inserted']   += $result['inserted'];
            $summary['updated']    += $result['updated'];
            $summary['skipped']    += $result['skipped'];
            $summary['errors']      = array_merge($summary['errors'], $result['errors']);

            Log::info('legacy_contacts_import.file_done', [
                'file'     => $sourceName,
                'read'     => $result['total_read'],
                'inserted' => $result['inserted'],
                'updated'  => $result['updated'],
                'skipped'  => $result['skipped'],
                'errors'   => count($result['errors']),
            ]);
        }

        Log::info('legacy_contacts_import.complete', [
            'batch'    => $batchId,
            'read'     => $summary['total_read'],
            'inserted' => $summary['inserted'],
            'updated'  => $summary['updated'],
            'skipped'  => $summary['skipped'],
            'errors'   => count($summary['errors']),
        ]);

        return $summary;
    }

    // ─────────────────────────────────────────────────────
    // Lectura y procesamiento de un CSV individual
    // ─────────────────────────────────────────────────────

    protected function importCsv(string $path, string $sourceName, string $batchId): array
    {
        $result = ['total_read' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (! file_exists($path) || ! is_readable($path)) {
            $result['errors'][] = "Archivo no encontrado o no legible: {$path}";
            Log::error('legacy_contacts_import.file_missing', ['path' => $path]);
            return $result;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $result['errors'][] = "No se pudo abrir: {$path}";
            return $result;
        }

        // Detectar BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);
            $result['errors'][] = "Sin encabezados en: {$path}";
            return $result;
        }

        $headerMap = $this->buildHeaderMap($rawHeaders);

        if (! in_array('id_number', $headerMap, true) || ! in_array('full_name', $headerMap, true)) {
            fclose($handle);
            $result['errors'][] = "La hoja no contiene los encabezados esperados (id_number, full_name).";
            return $result;
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNum++;
            $result['total_read']++;

            if ($this->isEmptyRow($row)) {
                $result['skipped']++;
                continue;
            }

            try {
                $mapped  = $this->mapRowToFields($row, $headerMap, $rawHeaders);
                $payload = $this->buildContactPayload($mapped, $sourceName, $batchId, $rowNum);

                if ($payload === null) {
                    $result['skipped']++;
                    continue;
                }

                $isUpdate = false;

                DB::transaction(function () use ($payload, &$isUpdate) {
                    $contact = Contact::withTrashed()
                        ->where('id_number', $payload['id_number'])
                        ->first();

                    if ($contact) {
                        $isUpdate = true;
                    } else {
                        $contact = new Contact();
                    }

                    $contact->fill($payload);
                    $contact->deleted_at = null;
                    $contact->save();
                });

                $isUpdate ? $result['updated']++ : $result['inserted']++;

                if (($result['inserted'] + $result['updated']) % 500 === 0) {
                    Log::info('legacy_contacts_import.progress', [
                        'file' => $sourceName, 'row' => $rowNum,
                        'inserted' => $result['inserted'], 'updated' => $result['updated'],
                    ]);
                }
            } catch (\Throwable $e) {
                $errorMsg = "Fila {$rowNum}: {$e->getMessage()}";
                $result['errors'][] = $errorMsg;
                $result['skipped']++;
                Log::warning('legacy_contacts_import.row_error', [
                    'file' => $sourceName, 'row' => $rowNum, 'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);
        return $result;
    }

    // ─────────────────────────────────────────────────────
    // Mapeo de encabezados
    // ─────────────────────────────────────────────────────

    protected function buildHeaderMap(array $rawHeaders): array
    {
        $map = [];
        foreach ($rawHeaders as $idx => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized === '') {
                continue;
            }
            $map[$idx] = self::COLUMN_MAP[$normalized] ?? ('_unmapped:' . $normalized);
        }
        return $map;
    }

    protected function normalizeHeader(string $value): string
    {
        $val = preg_replace('/[[:cntrl:]]+/u', ' ', $value) ?? $value;
        $val = preg_replace('/\p{Z}+/u', ' ', $val) ?? $val;
        $val = trim($val);

        $val = strtr($val, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
        ]);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $val);
        $result = ($transliterated !== false && $transliterated !== '') ? $transliterated : $val;
        $result = strtoupper($result);

        $result = preg_replace('/[\'"`´’‘"“”()\[\].,;:\/\\\\-]+/u', ' ', $result) ?? $result;
        $result = preg_replace('/\s+/u', ' ', trim($result)) ?? trim($result);

        return $result;
    }

    protected function mapRowToFields(array $row, array $headerMap, array $rawHeaders): array
    {
        $fields = [];
        foreach ($headerMap as $idx => $fieldName) {
            $fields[$fieldName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
        }

        $rawRow = [];
        foreach ($rawHeaders as $idx => $header) {
            $rawRow[trim($header)] = $row[$idx] ?? '';
        }
        $fields['_raw_row'] = $rawRow;

        return $fields;
    }

    protected function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));
    }

    // ─────────────────────────────────────────────────────
    // Construcción del payload de Contact
    // ─────────────────────────────────────────────────────

    protected function buildContactPayload(array $mapped, string $sourceName, string $batchId, int $rowNum): ?array
    {
        $idType   = $this->resolveIdType($mapped['id_type'] ?? '');
        $idNumber = $this->normalizeIdNumber($mapped['id_number'] ?? '', $idType);

        if ($idNumber === '') {
            Log::info('legacy_contacts_import.skipped_no_id', ['row' => $rowNum, 'source' => $sourceName]);
            return null;
        }

        $fullName = trim($mapped['full_name'] ?? '');
        if ($fullName === '') {
            throw new \RuntimeException('El tercero no tiene razón social o nombre completo.');
        }

        $email = $this->reserveEmail(
            $idNumber,
            $mapped['email'] ?? '',
        );

        $address = $this->mergeAddressWithPostalCode(
            $mapped['address'] ?? '',
            $mapped['_postal_code'] ?? '',
        );

        return [
            'contact_type'       => $this->resolveContactType($mapped['contact_type'] ?? ''),
            'id_type'            => $idType,
            'id_number'          => $idNumber,
            'dv'                 => $this->resolveDv($idType, $idNumber, $mapped['dv'] ?? ''),
            'full_name'          => $fullName,
            'person_type'        => $this->resolvePersonType($mapped['person_type'] ?? ''),
            'groups'             => $this->resolveCategoryIds($mapped['_category'] ?? '', $idNumber),
            'municipality'       => $mapped['municipality'] !== '' ? $mapped['municipality'] : null,
            'department'         => ($mapped['department'] ?? '') !== '' ? $mapped['department'] : null,
            'address'            => $address !== '' ? $address : null,
            'email'              => $email,
            'phone'              => ($mapped['phone'] ?? '') !== '' ? $mapped['phone'] : null,
            'additional_contacts' => $this->buildAdditionalContacts($mapped, $idNumber),
            'branches'           => $this->buildBranches($mapped, $idNumber, $mapped['phone'] ?? ''),
        ];
    }

    // ─────────────────────────────────────────────────────
    // Resolvers
    // ─────────────────────────────────────────────────────

    protected function resolveContactType(string $value): string
    {
        $n = $this->normalizeText($value);
        return match (true) {
            str_contains($n, 'CLIENTE') && str_contains($n, 'PROVEEDOR') => 'cliente_proveedor',
            str_contains($n, 'PROVEEDOR')                                 => 'proveedor',
            str_contains($n, 'CLIENTE')                                   => 'cliente',
            default                                                        => 'cliente_proveedor',
        };
    }

    protected function resolveIdType(string $value): string
    {
        $n = $this->normalizeText($value);
        return match (true) {
            $n === 'NIT', str_contains($n, 'TRIBUTARIA')        => 'nit',
            $n === 'CC', str_contains($n, 'CIUDADANIA')         => 'cc',
            $n === 'CE', str_contains($n, 'EXTRANJERIA')        => 'ce',
            $n === 'TE'                                          => 'te',
            $n === 'TI'                                          => 'ti',
            $n === 'RC'                                          => 'rc',
            $n === 'NUIP'                                        => 'nuip',
            $n === 'PP', str_contains($n, 'PASAPORTE')          => 'pp',
            $n === 'PPT', str_contains($n, 'PROTECCION TEMPORAL') => 'ppt',
            $n === 'PEP'                                         => 'pep',
            $n === 'DIE'                                         => 'die',
            str_contains($n, 'OTRO PAIS')                       => 'nit_otro_pais',
            default                                              => 'nit',
        };
    }

    protected function resolvePersonType(string $value): string
    {
        $n = $this->normalizeText($value);
        return match (true) {
            str_contains($n, 'JURIDICA') => 'persona_juridica',
            str_contains($n, 'NATURAL')  => 'persona_natural',
            default                       => 'persona_juridica',
        };
    }

    protected function resolveDv(string $idType, string $idNumber, string $dv): ?string
    {
        $clean = trim($dv);

        if ($idType !== 'nit') {
            return $clean !== '' ? $clean : null;
        }

        if ($clean !== '') {
            return $clean;
        }

        $numeric = preg_replace('/\D+/', '', $idNumber) ?? '';
        if ($numeric === '') {
            return null;
        }

        return (string) NitHelper::calcularDV($numeric);
    }

    protected function resolveCategoryIds(string $rawValue, string $idNumber): array
    {
        if (trim($rawValue) === '') {
            return [];
        }

        $ids    = [];
        $chunks = preg_split('/[;,|]+/', $rawValue) ?: [];

        foreach ($chunks as $chunk) {
            $normalized = $this->normalizeText($chunk);
            if ($normalized === '') {
                continue;
            }

            if (! isset($this->categoryGroupCache[$normalized])) {
                Log::info('legacy_contacts_import.category_not_found', [
                    'category' => $chunk,
                    'id_number' => $idNumber,
                ]);
                continue;
            }

            $ids[$this->categoryGroupCache[$normalized]] = $this->categoryGroupCache[$normalized];
        }

        return array_values($ids);
    }

    protected function normalizeIdNumber(string $value, string $idType): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return match ($idType) {
            'nit', 'cc', 'ce', 'te', 'ti', 'rc', 'nuip', 'ppt'
                => preg_replace('/\D+/', '', $trimmed) ?? '',
            default
                => strtoupper(preg_replace('/\s+/', '', $trimmed) ?? ''),
        };
    }

    protected function reserveEmail(string $idNumber, string $email): string
    {
        $candidate = mb_strtolower(trim($email));

        if ($candidate === '' || ! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $candidate = "{$idNumber}@" . self::EMAIL_DOMAIN;
            Log::info('legacy_contacts_import.email_generated', [
                'id_number' => $idNumber, 'generated' => $candidate,
            ]);
        }

        $owner = $this->emailOwners[$candidate] ?? null;
        if ($owner === null || $owner === $idNumber) {
            $this->emailOwners[$candidate] = $idNumber;
            return $candidate;
        }

        // Resolver colisión
        $baseLocal = strstr($candidate, '@', true) ?: $idNumber;
        $domain    = substr(strstr($candidate, '@') ?: ('@' . self::EMAIL_DOMAIN), 1);
        $suffix    = 2;

        do {
            $generated = "{$baseLocal}+{$suffix}@{$domain}";
            $owner     = $this->emailOwners[$generated] ?? null;
            $suffix++;
        } while ($owner !== null && $owner !== $idNumber);

        $this->emailOwners[$generated] = $idNumber;
        Log::info('legacy_contacts_import.email_collision_resolved', [
            'id_number' => $idNumber, 'original' => $candidate, 'assigned' => $generated,
        ]);

        return $generated;
    }

    protected function mergeAddressWithPostalCode(string $address, string $postalCode): string
    {
        $address    = trim($address);
        $postalCode = trim($postalCode);

        if ($postalCode === '') {
            return $address;
        }
        if ($address === '') {
            return "Código postal: {$postalCode}";
        }
        if (stripos($address, $postalCode) !== false) {
            return $address;
        }

        return "{$address} | Código postal: {$postalCode}";
    }

    protected function buildAdditionalContacts(array $mapped, string $idNumber): array
    {
        $name  = trim($mapped['_add_contact_name']  ?? '');
        $role  = trim($mapped['_add_contact_role']  ?? '');
        $phone = trim($mapped['_add_contact_phone'] ?? '');
        $email = trim($mapped['_add_contact_email'] ?? '');

        if ($name === '' && $phone === '' && $email === '' && $role === '') {
            return [];
        }

        if ($role !== '' && stripos($name, $role) === false) {
            $name = $name !== '' ? "{$name} ({$role})" : $role;
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('legacy_contacts_import.invalid_additional_email', [
                'id_number' => $idNumber, 'email' => $email,
            ]);
            $email = '';
        }

        return [['name' => $name, 'phone' => $phone, 'email' => $email]];
    }

    protected function buildBranches(array $mapped, string $idNumber, string $fallbackPhone): array
    {
        $name    = trim($mapped['_branch_name']    ?? '');
        $address = trim($mapped['_branch_address'] ?? '');

        if ($name === '' && $address === '') {
            return [];
        }

        return [[
            'name'    => $name !== '' ? $name : 'Sucursal principal',
            'address' => $address,
            'phone'   => $fallbackPhone,
        ]];
    }

    // ─────────────────────────────────────────────────────
    // Carga de caches al inicio (evita N+1)
    // ─────────────────────────────────────────────────────

    protected function warmCaches(): void
    {
        // Pre-cargar grupos de categoría existentes
        CategoryGroup::all(['id', 'name'])->each(function (CategoryGroup $group) {
            $key = $this->normalizeText($group->name);
            if ($key !== '') {
                $this->categoryGroupCache[$key] = (int) $group->id;
            }
        });

        // Pre-cargar emails de contactos existentes
        Contact::withTrashed()
            ->get(['id_number', 'email'])
            ->filter(fn (Contact $c): bool => trim((string) $c->email) !== '')
            ->each(function (Contact $c) {
                $this->emailOwners[mb_strtolower((string) $c->email)] = (string) $c->id_number;
            });

        Log::info('legacy_contacts_import.caches_warmed', [
            'category_groups' => count($this->categoryGroupCache),
            'existing_emails' => count($this->emailOwners),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────

    protected function normalizeText(string $value): string
    {
        $val = preg_replace('/[[:cntrl:]]+/u', ' ', $value) ?? $value;
        $val = preg_replace('/\p{Z}+/u', ' ', $val) ?? $val;
        $val = trim($val);

        $val = strtr($val, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
        ]);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $val);
        $result = ($transliterated !== false && $transliterated !== '') ? $transliterated : $val;
        $result = strtoupper($result);

        $result = preg_replace('/[\'"`´’‘"“”()\[\].,;:\/\\\\-]+/u', ' ', $result) ?? $result;
        $result = preg_replace('/\s+/u', ' ', trim($result)) ?? trim($result);

        return $result;
    }
}
