<?php

namespace App\Providers;

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use App\Models\Expenses\PurchaseRequest;
use App\Models\MasterData\Item;
use App\Models\Settings\CompanySetting;
use App\Models\Finance\Retention;
use App\Models\Settings\User;
use App\Models\Production\Programmer;
use App\Observers\AuditObserver;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Observers\PurchaseDocumentInventoryObserver;

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

        //se debe agregar un observer para cada modelo que se quiera auditar
        PurchaseRequest::observe(AuditObserver::class);
        Item::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
        CategoryGroup::observe(AuditObserver::class);
        CategorySubGroup::observe(AuditObserver::class);
        Contact::observe(AuditObserver::class);
        Tax::observe(AuditObserver::class);
        Warehouse::observe(AuditObserver::class);
        CompanySetting::observe(AuditObserver::class);
        Retention::observe(AuditObserver::class);
        PurchaseDocument::observe(AuditObserver::class);
        PurchaseDocument::observe(PurchaseDocumentInventoryObserver::class);
        PurchaseDocumentItem::observe(AuditObserver::class);
    }

}
