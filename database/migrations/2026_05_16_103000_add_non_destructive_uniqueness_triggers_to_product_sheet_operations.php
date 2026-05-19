<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_operation_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_operation_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_sequence_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_sequence_update');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pso_prevent_duplicate_operation_insert
BEFORE INSERT ON product_sheet_operations
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NULL THEN
        IF EXISTS (
            SELECT 1
            FROM product_sheet_operations pso
            WHERE pso.product_sheet_id = NEW.product_sheet_id
              AND pso.operation_id = NEW.operation_id
              AND pso.deleted_at IS NULL
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No se permite duplicar operaciones en la misma sábana.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pso_prevent_duplicate_operation_update
BEFORE UPDATE ON product_sheet_operations
FOR EACH ROW
BEGIN
    IF (
        NEW.product_sheet_id <> OLD.product_sheet_id
        OR NEW.operation_id <> OLD.operation_id
        OR (NEW.deleted_at IS NULL AND OLD.deleted_at IS NOT NULL)
    ) THEN
        IF NEW.deleted_at IS NULL THEN
            IF EXISTS (
                SELECT 1
                FROM product_sheet_operations pso
                WHERE pso.product_sheet_id = NEW.product_sheet_id
                  AND pso.operation_id = NEW.operation_id
                  AND pso.deleted_at IS NULL
                  AND pso.id <> OLD.id
                LIMIT 1
            ) THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'No se permite duplicar operaciones en la misma sábana.';
            END IF;
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pso_prevent_duplicate_sequence_insert
BEFORE INSERT ON product_sheet_operations
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NULL THEN
        IF EXISTS (
            SELECT 1
            FROM product_sheet_operations pso
            WHERE pso.product_sheet_id = NEW.product_sheet_id
              AND pso.sequence = NEW.sequence
              AND pso.deleted_at IS NULL
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No se permite duplicar secuencias en la misma sábana.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pso_prevent_duplicate_sequence_update
BEFORE UPDATE ON product_sheet_operations
FOR EACH ROW
BEGIN
    IF (
        NEW.product_sheet_id <> OLD.product_sheet_id
        OR NEW.sequence <> OLD.sequence
        OR (NEW.deleted_at IS NULL AND OLD.deleted_at IS NOT NULL)
    ) THEN
        IF NEW.deleted_at IS NULL THEN
            IF EXISTS (
                SELECT 1
                FROM product_sheet_operations pso
                WHERE pso.product_sheet_id = NEW.product_sheet_id
                  AND pso.sequence = NEW.sequence
                  AND pso.deleted_at IS NULL
                  AND pso.id <> OLD.id
                LIMIT 1
            ) THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'No se permite duplicar secuencias en la misma sábana.';
            END IF;
        END IF;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_operation_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_operation_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_sequence_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pso_prevent_duplicate_sequence_update');
    }
};
