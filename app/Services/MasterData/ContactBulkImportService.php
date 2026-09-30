<?php

namespace App\Services\MasterData;

use App\Helpers\NitHelper;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\Contact;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la carga masiva de contactos desde el arreglo generado al leer el
 * Excel (plantilla vacía o archivo previamente exportado). Sigue el mismo
 * patrón que ItemService::processBulkUploadArray().
 */
class ContactBulkImportService
{
    /** @var array<string, int> nombre normalizado → id */
    private array $groupIdByName = [];

    /**
     * @param  array<int, array<int|string, mixed>>  $rows  Filas del sheet, indexadas por letra de columna (A, B, C...) o por índice numérico.
     * @return array{inserted: int, updated: int, errors: array<int, string>}
     */
    public function processBulkUploadArray(array $rows): array
    {
        $inserted = 0;
        $updated = 0;
        $errors = [];

        if (empty($rows)) {
            return compact('inserted', 'updated', 'errors');
        }

        $this->warmGroupCache();

        $headerRow = array_shift($rows);
        $headerMap = $this->buildHeaderMap($headerRow);

        if (! isset($headerMap['NUMERO DE IDENTIFICACION']) || ! isset($headerMap['NOMBRE COMPLETO'])) {
            $errors[] = 'El archivo no contiene los encabezados esperados (NUMERO DE IDENTIFICACION, NOMBRE COMPLETO).';

            return compact('inserted', 'updated', 'errors');
        }

        $insertedIds = [];
        $insertedIdNumbers = [];
        $updatedIds = [];
        $updatedIdNumbers = [];

        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $data = $this->mapRow($row, $headerMap);

            try {
                $payload = $this->buildPayload($data, $rowNum);

                if ($payload === null) {
                    continue;
                }

                $idNumber = $payload['id_number'];
                $existing = DB::table('contacts')->where('id_number', $idNumber)->first();

                $emailOwnedByOther = DB::table('contacts')
                    ->where('email', $payload['email'])
                    ->where('id_number', '!=', $idNumber)
                    ->exists();

                if ($emailOwnedByOther) {
                    $errors[] = "Fila {$rowNum}: el correo '{$payload['email']}' ya pertenece a otro contacto.";

                    continue;
                }

                if ($existing) {
                    $payload['updated_at'] = now();
                    DB::table('contacts')->where('id_number', $idNumber)->update($payload);
                    $updatedIds[] = $existing->id;
                    $updatedIdNumbers[] = $idNumber;
                    $updated++;
                } else {
                    $payload['created_at'] = now();
                    $payload['updated_at'] = now();
                    $insertedIds[] = DB::table('contacts')->insertGetId($payload);
                    $insertedIdNumbers[] = $idNumber;
                    $inserted++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Fila {$rowNum}: {$e->getMessage()}";
            }
        }

        // La carga por query builder no dispara el AuditObserver: se registra un resumen.
        $audit = app(AuditLogService::class);
        $fields = ['id_number', 'contact_type', 'id_type', 'dv', 'full_name', 'person_type', 'groups', 'municipality', 'department', 'address', 'email', 'phone', 'additional_contacts', 'branches'];
        $audit->logBulk(Contact::class, 'created', $insertedIds, ['id_numbers' => $insertedIdNumbers, 'campos' => $fields], 'Carga masiva de contactos desde Excel');
        $audit->logBulk(Contact::class, 'updated', $updatedIds, ['id_numbers' => $updatedIdNumbers, 'campos' => $fields], 'Carga masiva de contactos desde Excel');

        return compact('inserted', 'updated', 'errors');
    }

    /**
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $colKey => $value) {
            $normalized = mb_strtoupper(trim((string) $value), 'UTF-8');
            if ($normalized === '') {
                continue;
            }
            $map[$normalized] = $colKey;
        }

        return $map;
    }

    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));
    }

    /**
     * @return array<string, string>
     */
    private function mapRow(array $row, array $headerMap): array
    {
        $data = [];
        foreach ($headerMap as $header => $colKey) {
            $data[$header] = isset($row[$colKey]) ? trim((string) $row[$colKey]) : '';
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>|null
     */
    private function buildPayload(array $data, int $rowNum): ?array
    {
        $idNumber = preg_replace('/\s+/', '', $data['NUMERO DE IDENTIFICACION'] ?? '') ?? '';
        if ($idNumber === '') {
            throw new \RuntimeException('NUMERO DE IDENTIFICACION es obligatorio.');
        }

        $fullName = $data['NOMBRE COMPLETO'] ?? '';
        if ($fullName === '') {
            throw new \RuntimeException('NOMBRE COMPLETO es obligatorio.');
        }

        $contactType = $this->resolveEnum(
            $data['TIPO DE CONTACTO'] ?? '',
            Contact::CONTACT_TYPES,
            'TIPO DE CONTACTO'
        );

        $idType = $this->resolveEnum(
            $data['TIPO DE IDENTIFICACION'] ?? '',
            Contact::ID_TYPES,
            'TIPO DE IDENTIFICACION'
        );

        $personType = $this->resolveEnum(
            $data['TIPO DE PERSONA'] ?? '',
            Contact::PERSON_TYPES,
            'TIPO DE PERSONA'
        );

        $dv = $data['DV'] ?? '';
        if ($dv === '' && $idType === 'nit') {
            $numeric = preg_replace('/\D+/', '', $idNumber) ?? '';
            $dv = $numeric !== '' ? (string) NitHelper::calcularDV($numeric) : null;
        }

        $email = trim($data['EMAIL'] ?? '');
        if ($email === '') {
            $email = "{$idNumber}@import.local";
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("El correo '{$email}' no es válido.");
        }

        return [
            'id_number' => $idNumber,
            'contact_type' => $contactType,
            'id_type' => $idType,
            'dv' => $dv !== '' ? $dv : null,
            'full_name' => $fullName,
            'person_type' => $personType,
            'groups' => json_encode($this->resolveGroupIds($data['GRUPOS'] ?? '')),
            'municipality' => ($data['MUNICIPIO'] ?? '') !== '' ? $data['MUNICIPIO'] : null,
            'department' => ($data['DEPARTAMENTO'] ?? '') !== '' ? $data['DEPARTAMENTO'] : null,
            'address' => ($data['DIRECCION'] ?? '') !== '' ? $data['DIRECCION'] : null,
            'email' => $email,
            'phone' => ($data['TELEFONO'] ?? '') !== '' ? $data['TELEFONO'] : null,
            'additional_contacts' => json_encode($this->buildAdditionalContacts($data)),
            'branches' => json_encode($this->buildBranches($data)),
        ];
    }

    /**
     * @param  array<int, string>  $validValues
     */
    private function resolveEnum(string $raw, array $validValues, string $columnLabel): string
    {
        $normalized = mb_strtolower(trim($raw), 'UTF-8');
        $normalized = str_replace(' ', '_', $normalized);

        if (! in_array($normalized, $validValues, true)) {
            throw new \RuntimeException("{$columnLabel} '{$raw}' no es válido.");
        }

        return $normalized;
    }

    /**
     * @return array<int, int>
     */
    private function resolveGroupIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[;,]+/', $raw) ?: [] as $chunk) {
            $key = mb_strtoupper(trim($chunk), 'UTF-8');
            if ($key === '') {
                continue;
            }
            if (isset($this->groupIdByName[$key])) {
                $ids[$this->groupIdByName[$key]] = $this->groupIdByName[$key];
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, array<string, string>>
     */
    private function buildAdditionalContacts(array $data): array
    {
        $name = $data['CONTACTO ADICIONAL NOMBRE'] ?? '';
        $phone = $data['CONTACTO ADICIONAL TELEFONO'] ?? '';
        $email = $data['CONTACTO ADICIONAL EMAIL'] ?? '';

        if ($name === '' && $phone === '' && $email === '') {
            return [];
        }

        return [['name' => $name, 'phone' => $phone, 'email' => $email]];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, array<string, string>>
     */
    private function buildBranches(array $data): array
    {
        $name = $data['SUCURSAL NOMBRE'] ?? '';
        $address = $data['SUCURSAL DIRECCION'] ?? '';
        $phone = $data['SUCURSAL TELEFONO'] ?? '';

        if ($name === '' && $address === '' && $phone === '') {
            return [];
        }

        return [['name' => $name, 'address' => $address, 'phone' => $phone]];
    }

    private function warmGroupCache(): void
    {
        CategoryGroup::query()->get(['id', 'name'])->each(function (CategoryGroup $group) {
            $key = mb_strtoupper(trim($group->name), 'UTF-8');
            if ($key !== '') {
                $this->groupIdByName[$key] = (int) $group->id;
            }
        });
    }
}
