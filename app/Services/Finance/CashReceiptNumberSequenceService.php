<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashReceiptNumberSequenceService
{
    private const DOCUMENT_TYPE = 'cash_receipt';

    public function reserveNextNumber(): int
    {
        return DB::transaction(function () {
            $sequence = DB::table('finance_document_sequences')
                ->where('document_type', self::DOCUMENT_TYPE)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new RuntimeException('No existe la secuencia para recibo de caja.');
            }

            $nextValue = (int) $sequence->current_value + 1;

            DB::table('finance_document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => now(),
                ]);

            return $nextValue;
        });
    }
}
