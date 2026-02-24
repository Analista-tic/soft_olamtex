<?php

namespace App\Providers;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Finance\Retention;
use App\Models\Finance\Tax;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryRequest;
use App\Models\Inventory\InventoryRequestItem;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseTransfer;
use App\Models\Inventory\WarehouseTransferItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\MasterData\Parameter;
use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use App\Models\Production\Programmer;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\Area;
use App\Models\Settings\User;
use App\Models\Settings\Role;
use App\Models\Settings\Setting;
use App\Models\Textil\FabricOrder;
use App\Models\Textil\FabricOrderItem;
use App\Models\Textil\FabricProvider;
use App\Models\Textil\FabricRoll;
use App\Observers\AuditObserver;
use App\Observers\PurchaseDocumentInventoryObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Settings\CompanySetting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191); // Limita las cadenas a 191 caracteres

        Route::model('purchaseDocument', PurchaseDocument::class);
        Route::model('programador', Programmer::class);

        $this->registerAuditObservers();
        PurchaseDocument::observe(PurchaseDocumentInventoryObserver::class);
    }

    private function registerAuditObservers(): void
    {
        $auditedModels = [
            // Settings
            User::class,
            CompanySetting::class,
            Setting::class,
            Area::class,
            Role::class,
            // MasterData
            CategoryGroup::class,
            CategorySubGroup::class,
            Contact::class,
            Item::class,
            Parameter::class,
            // Finance
            Tax::class,
            Retention::class,
            // Expenses
            PurchaseRequest::class,
            PurchaseDocument::class,
            PurchaseDocumentItem::class,
            // Inventory
            Warehouse::class,
            WarehouseTransfer::class,
            WarehouseTransferItem::class,
            InventoryRequest::class,
            InventoryRequestItem::class,
            InventoryAdjustment::class,
            InventoryAdjustmentLine::class,
            InventoryMovement::class,
            // Production
            ProductSheet::class,
            ProductOperationList::class,
            Operation::class,
            OperationSection::class,
            Machine::class,
            Programmer::class,
            // Textil
            FabricRoll::class,
            FabricOrder::class,
            FabricOrderItem::class,
            FabricProvider::class,
        ];

        foreach ($auditedModels as $modelClass) {
            $modelClass::observe(AuditObserver::class);
        }
    }
}
