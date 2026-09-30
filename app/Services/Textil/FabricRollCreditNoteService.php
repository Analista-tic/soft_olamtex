<?php

namespace App\Services\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentReceptionEvent;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollCreditNoteLink;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enlace entre rollos rechazados en recepción y la nota crédito (manual) que los cubre.
 *
 * Reglas (ver docs/RECEPCION_ROLLOS_TOLERANCIA_Y_NOTAS_CREDITO.md):
 *  - Solo una nota CRÉDITO de compras, cuyo padre es una factura de tela, puede cubrir rollos.
 *  - Los rollos deben pertenecer a esa factura, estar `rejected` y sin resolver, y sin otro enlace activo.
 *  - Al aprobarse la nota, los rollos enlazados se dan de baja (sin movimiento de inventario: nunca entraron al stock).
 *  - El monto de los rollos rechazados sin resolver es "monto en disputa" y se retiene del saldo pagable.
 */
class FabricRollCreditNoteService
{
    /**
     * Sincroniza los rollos que cubre la nota: deja exactamente $rollIds enlazados.
     *
     * @param  array<int, int|string>  $rollIds
     */
    public function linkRolls(PurchaseDocument $note, array $rollIds): void
    {
        $rollIds = collect($rollIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();

        DB::transaction(function () use ($note, $rollIds) {
            $existingIds = FabricRollCreditNoteLink::query()
                ->where('credit_note_document_id', $note->id)
                ->pluck('fabric_roll_id')
                ->map(fn ($id) => (int) $id);

            if ($rollIds->isEmpty()) {
                $this->unlinkAll($note);

                return;
            }

            if ($note->document_type !== PurchaseDocument::TYPE_CREDIT_NOTE) {
                throw ValidationException::withMessages([
                    'fabric_roll_ids' => 'Solo una nota crédito puede cubrir rollos rechazados; una nota débito no.',
                ]);
            }

            $invoice = PurchaseDocument::query()->find((int) $note->parent_document_id);
            if (! $invoice || $invoice->document_type !== PurchaseDocument::TYPE_INVOICE || ! $this->isFabricInvoice($invoice)) {
                throw ValidationException::withMessages([
                    'fabric_roll_ids' => 'Los rollos solo se pueden enlazar a una nota de una factura de tela.',
                ]);
            }

            $rolls = FabricRoll::query()
                ->whereIn('id', $rollIds->all())
                ->with('purchaseDocumentItem:id,purchase_document_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($rollIds as $rollId) {
                $roll = $rolls->get($rollId);
                if (! $roll) {
                    throw ValidationException::withMessages([
                        'fabric_roll_ids' => "El rollo {$rollId} no existe.",
                    ]);
                }

                $label = $roll->roll_number ?: '#'.$roll->id;

                if ((int) ($roll->purchaseDocumentItem?->purchase_document_id ?? 0) !== (int) $invoice->id) {
                    throw ValidationException::withMessages([
                        'fabric_roll_ids' => "El rollo {$label} no pertenece a la factura {$invoice->document_number}.",
                    ]);
                }

                if ($roll->tolerance_status !== FabricRoll::TOLERANCE_REJECTED || $roll->resolved_at !== null) {
                    throw ValidationException::withMessages([
                        'fabric_roll_ids' => "El rollo {$label} no está rechazado o ya fue resuelto; solo se enlazan rollos rechazados sin resolver.",
                    ]);
                }

                $otherLink = FabricRollCreditNoteLink::query()
                    ->where('fabric_roll_id', $roll->id)
                    ->where('credit_note_document_id', '!=', $note->id)
                    ->whereHas('creditNote') // ignora notas eliminadas (soft delete)
                    ->with('creditNote:id,document_number')
                    ->first();
                if ($otherLink) {
                    $otherNumber = $otherLink->creditNote?->document_number ?? '#'.$otherLink->credit_note_document_id;
                    throw ValidationException::withMessages([
                        'fabric_roll_ids' => "El rollo {$label} ya está enlazado a la nota {$otherNumber}.",
                    ]);
                }
            }

            // Quitar los que ya no van y agregar los nuevos.
            $toRemove = $existingIds->diff($rollIds);
            if ($toRemove->isNotEmpty()) {
                FabricRollCreditNoteLink::query()
                    ->where('credit_note_document_id', $note->id)
                    ->whereIn('fabric_roll_id', $toRemove->all())
                    ->delete();
            }

            foreach ($rollIds->diff($existingIds) as $rollId) {
                FabricRollCreditNoteLink::query()->create([
                    'fabric_roll_id' => $rollId,
                    'credit_note_document_id' => $note->id,
                    'created_by_user_id' => Auth::id(),
                ]);

                $roll = $rolls->get($rollId);
                $this->recordEvent(
                    (int) $invoice->id,
                    PurchaseDocumentReceptionEvent::TYPE_ROLL_LINKED_TO_NOTE,
                    null,
                    'Rollo '.($roll->roll_number ?: '#'.$roll->id).' enlazado a la nota crédito '.($note->document_number ?: '#'.$note->id).'.',
                );
            }
        });
    }

    /** Borra todos los enlaces de la nota (al eliminarla o al dejar de ser crédito). */
    public function unlinkAll(PurchaseDocument $note): void
    {
        FabricRollCreditNoteLink::query()
            ->where('credit_note_document_id', $note->id)
            ->delete();
    }

    /**
     * Al aprobarse la nota crédito, los rollos enlazados se dan de baja. No hay movimiento de inventario
     * que revertir: un rollo rechazado nunca entró al stock disponible.
     */
    public function resolveOnApproval(PurchaseDocument $note): int
    {
        if ($note->document_type !== PurchaseDocument::TYPE_CREDIT_NOTE) {
            return 0;
        }

        return DB::transaction(function () use ($note) {
            $rollIds = FabricRollCreditNoteLink::query()
                ->where('credit_note_document_id', $note->id)
                ->pluck('fabric_roll_id');

            $rolls = FabricRoll::query()
                ->whereIn('id', $rollIds->all())
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->get();

            $now = now();
            foreach ($rolls as $roll) {
                $roll->forceFill(['resolved_at' => $now, 'written_off_at' => $now])->save();

                $this->recordEvent(
                    (int) $note->parent_document_id,
                    PurchaseDocumentReceptionEvent::TYPE_ROLL_WRITTEN_OFF,
                    $roll->tolerance_reason,
                    'Rollo '.($roll->roll_number ?: '#'.$roll->id).' dado de baja al aprobarse la nota crédito '.($note->document_number ?: '#'.$note->id).'.',
                );
            }

            return $rolls->count();
        });
    }

    /**
     * Rollos rechazados sin resolver de la factura, con su monto y la nota a la que están enlazados (si alguna).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendingRejectedRolls(PurchaseDocument $invoice): Collection
    {
        return $this->pendingRollsQuery([(int) $invoice->id])
            ->orderBy('id')
            ->get()
            ->map(function (FabricRoll $roll) {
                $link = $roll->creditNoteLinks->first(fn (FabricRollCreditNoteLink $l) => $l->creditNote !== null);

                return [
                    'id' => (int) $roll->id,
                    'roll_number' => $roll->roll_number,
                    'quantity' => round((float) $roll->quantity, 2),
                    'amount' => $this->rollAmount($roll),
                    'reason' => $roll->tolerance_reason,
                    'credit_note_id' => $link?->credit_note_document_id,
                    'credit_note_number' => $link?->creditNote?->document_number,
                ];
            })
            ->values();
    }

    /** Monto retenido (rollos rechazados sin resolver): cantidad × precio + impuesto proporcional de la línea. */
    public function disputedAmount(PurchaseDocument $invoice): float
    {
        return $this->disputedSummaries([(int) $invoice->id])[(int) $invoice->id]['amount'];
    }

    /** @return array{amount: float, rolls: int} */
    public function disputedSummary(PurchaseDocument $invoice): array
    {
        return $this->disputedSummaries([(int) $invoice->id])[(int) $invoice->id];
    }

    /**
     * Versión por lotes (una consulta) para listados como cuentas por pagar.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, array{amount: float, rolls: int}>
     */
    public function disputedSummaries(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        $result = [];
        foreach ($invoiceIds as $id) {
            $result[$id] = ['amount' => 0.0, 'rolls' => 0];
        }

        if ($invoiceIds === []) {
            return $result;
        }

        foreach ($this->pendingRollsQuery($invoiceIds)->get() as $roll) {
            $invoiceId = (int) $roll->purchaseDocumentItem->purchase_document_id;
            $result[$invoiceId]['amount'] += $this->rollAmount($roll);
            $result[$invoiceId]['rolls']++;
        }

        foreach ($result as $id => $row) {
            $result[$id]['amount'] = round($row['amount'], 2);
        }

        return $result;
    }

    /** Rollos vinculados a la nota (para el payload de la nota). */
    public function linkedRolls(PurchaseDocument $note): Collection
    {
        return FabricRoll::query()
            ->whereIn('id', FabricRollCreditNoteLink::query()
                ->where('credit_note_document_id', $note->id)
                ->pluck('fabric_roll_id'))
            ->with('purchaseDocumentItem:id,purchase_document_id,price,tax,total,total_tax')
            ->orderBy('id')
            ->get()
            ->map(fn (FabricRoll $roll) => [
                'id' => (int) $roll->id,
                'roll_number' => $roll->roll_number,
                'quantity' => round((float) $roll->quantity, 2),
                'amount' => $this->rollAmount($roll),
                'reason' => $roll->tolerance_reason,
                'resolved_at' => $roll->resolved_at?->toDateTimeString(),
                'written_off_at' => $roll->written_off_at?->toDateTimeString(),
            ])
            ->values();
    }

    private function pendingRollsQuery(array $invoiceIds)
    {
        return FabricRoll::query()
            ->where('tolerance_status', FabricRoll::TOLERANCE_REJECTED)
            ->whereNull('resolved_at')
            ->whereHas('purchaseDocumentItem', fn ($q) => $q->whereIn('purchase_document_id', $invoiceIds))
            ->with([
                'purchaseDocumentItem:id,purchase_document_id,price,tax,total,total_tax',
                'creditNoteLinks.creditNote:id,document_number',
            ]);
    }

    private function rollAmount(FabricRoll $roll): float
    {
        $line = $roll->purchaseDocumentItem;
        if (! $line) {
            return 0.0;
        }

        $price = $roll->price_per_meter !== null ? (float) $roll->price_per_meter : (float) ($line->price ?? 0);
        $base = (float) $roll->quantity * $price;

        $lineTotal = (float) ($line->total ?? 0);
        $taxRatio = $lineTotal > 0
            ? (float) ($line->total_tax ?? 0) / $lineTotal
            : (float) ($line->tax ?? 0) / 100;

        return round($base * (1 + $taxRatio), 2);
    }

    private function isFabricInvoice(PurchaseDocument $invoice): bool
    {
        return $invoice->items()->with('item.categorySubGroup')->get()
            ->contains(fn (PurchaseDocumentItem $line) => MaterialClassifier::isFabricPurchaseDocumentItem($line));
    }

    private function recordEvent(int $invoiceId, string $type, ?string $reason, ?string $notes): void
    {
        PurchaseDocumentReceptionEvent::create([
            'purchase_document_id' => $invoiceId,
            'event_type' => $type,
            'reason' => $reason,
            'notes' => $notes,
            'user_id' => Auth::id(),
            'occurred_at' => now(),
        ]);
    }
}
