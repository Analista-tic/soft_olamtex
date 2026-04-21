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
use App\Models\Production\Collection;
use App\Models\Production\Operation;
use App\Models\Production\OperationAction;
use App\Models\Production\OperationSection;
use App\Models\Production\OperationGarmentPart;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Production\TechnicalSheetProcess;
use App\Models\Production\TechnicalSheetRawMaterial;
use App\Models\Production\TechnicalSheetRoll;
use App\Models\Production\TechnicalSheetSupply;
use App\Models\Production\TechnicalDataSheetSupply;
use App\Models\Production\TechnicalSheetThread;
use App\Models\Settings\Area;
use App\Models\Settings\User;
use App\Models\Settings\Role;
use App\Models\Settings\Setting;
use App\Models\Textil\FabricRoll;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        Gate::before(function ($user, $ability) {
            $isAdmin = method_exists($user, 'roles')
                ? ($user->relationLoaded('roles')
                    ? $user->roles->contains('name', 'admin')
                    : $user->roles()->where('name', 'admin')->exists())
                : false;

            if ($isAdmin) {
                return true;
            }

            return null;
        });

        Schema::defaultStringLength(191); // Limita las cadenas a 191 caracteres

        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Route::model('purchaseDocument', PurchaseDocument::class);
        Route::model('programador', ProductionOrder::class);
        Route::model('listItem', ProductOperationList::class);
        Route::model('operation', Operation::class);
        Route::model('collection', Collection::class);

        $this->registerAuditObservers();

        Model::preventLazyLoading(! app()->isProduction());
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            $class = get_class($model);
            info("N+1 detectado: relación [{$relation}] en modelo [{$class}]");
            throw new \RuntimeException("N+1 detectado: relación [{$relation}] en modelo [{$class}]. Usa eager loading (->with()).");
        });
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
            Collection::class,
            ProductOperationList::class,
            Operation::class,
            OperationAction::class,
            OperationGarmentPart::class,
            OperationSection::class,
            Machine::class,
            ProductionOrder::class,
            TechnicalSheet::class,
            TechnicalSheetOperation::class,
            TechnicalSheetProcess::class,
            TechnicalSheetRoll::class,
            TechnicalSheetThread::class,
            TechnicalSheetSupply::class,
            TechnicalSheetRawMaterial::class,
            TechnicalDataSheetSupply::class,
            // Textil
            FabricRoll::class,
        ];

        foreach ($auditedModels as $modelClass) {
            $modelClass::observe(AuditObserver::class);
        }
    }
}
