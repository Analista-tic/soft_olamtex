<?php

namespace App\Providers;

use App\Models\Common\CategoryGroup;
use App\Models\Common\CategorySubGroup;
use App\Models\Common\Contact;
use App\Models\Common\Tax;
use App\Models\Common\Warehouse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use App\Models\Expenses\PurchaseRequest;
use App\Models\Inventory\Item;
use App\Models\Settings\CompanySetting;
use App\Models\Settings\Retention;
use App\Models\User;
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
