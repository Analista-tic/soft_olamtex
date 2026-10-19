<?php

use App\Models\MasterData\Item;
use App\Services\Inventory\InventoryMovementService;
use App\Services\MasterData\AdditionalFieldService;
use App\Services\MasterData\ItemService;

function invokeItemServiceOriginNormalizer(mixed $value): string
{
    $service = new ItemService(new AdditionalFieldService(), new InventoryMovementService());
    $method = new ReflectionMethod(ItemService::class, 'normalizeOriginType');

    return $method->invoke($service, $value);
}

function invokeShouldMarkEditedAfterImport(Item $item): bool
{
    $service = new ItemService(new AdditionalFieldService(), new InventoryMovementService());
    $method = new ReflectionMethod(ItemService::class, 'shouldMarkEditedAfterImport');

    return $method->invoke($service, $item);
}

it('normalizes unknown origins to system', function () {
    expect(invokeItemServiceOriginNormalizer(null))->toBe(Item::ORIGIN_SYSTEM)
        ->and(invokeItemServiceOriginNormalizer(''))->toBe(Item::ORIGIN_SYSTEM)
        ->and(invokeItemServiceOriginNormalizer('other'))->toBe(Item::ORIGIN_SYSTEM);
});

it('accepts migration as the only imported origin value', function () {
    expect(invokeItemServiceOriginNormalizer(Item::ORIGIN_MIGRATION))->toBe(Item::ORIGIN_MIGRATION);
});

it('exposes explicit item origin constants', function () {
    expect(Item::ORIGIN_SYSTEM)->toBe('system')
        ->and(Item::ORIGIN_MIGRATION)->toBe('migration');
});

it('marks only unedited migration items as edited after import', function () {
    $unmodifiedMigration = new Item([
        'origin_type' => Item::ORIGIN_MIGRATION,
        'was_edited_after_import' => false,
    ]);

    $alreadyEditedMigration = new Item([
        'origin_type' => Item::ORIGIN_MIGRATION,
        'was_edited_after_import' => true,
    ]);

    $systemItem = new Item([
        'origin_type' => Item::ORIGIN_SYSTEM,
        'was_edited_after_import' => false,
    ]);

    expect(invokeShouldMarkEditedAfterImport($unmodifiedMigration))->toBeTrue()
        ->and(invokeShouldMarkEditedAfterImport($alreadyEditedMigration))->toBeFalse()
        ->and(invokeShouldMarkEditedAfterImport($systemItem))->toBeFalse()
        ->and($unmodifiedMigration->shouldHighlightAsImported())->toBeTrue()
        ->and($alreadyEditedMigration->shouldHighlightAsImported())->toBeFalse()
        ->and($systemItem->shouldHighlightAsImported())->toBeFalse();
});
