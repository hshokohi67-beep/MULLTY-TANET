<?php

use App\Modules\Analytics\Providers\AnalyticsServiceProvider;
use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Commerce\Providers\CommerceServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\Customers\Providers\CustomersServiceProvider;
use App\Modules\Discounts\Providers\DiscountsServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Insights\Providers\InsightsServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Kitchen\Providers\KitchenServiceProvider;
use App\Modules\Loyalty\Providers\LoyaltyServiceProvider;
use App\Modules\Marketplace\Providers\MarketplaceServiceProvider;
use App\Modules\Operations\Providers\OperationsServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Storefront\Providers\StorefrontServiceProvider;
use App\Providers\AppServiceProvider;
use App\Support\Sms\SmsServiceProvider;
use App\Support\Tenancy\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    SmsServiceProvider::class,
    CoreServiceProvider::class,
    IdentityServiceProvider::class,
    CustomersServiceProvider::class,
    CatalogServiceProvider::class,
    CommerceServiceProvider::class,
    DiscountsServiceProvider::class,
    PaymentsServiceProvider::class,
    LoyaltyServiceProvider::class,
    KitchenServiceProvider::class,
    StorefrontServiceProvider::class,
    InventoryServiceProvider::class,
    OperationsServiceProvider::class,
    AnalyticsServiceProvider::class,
    BillingServiceProvider::class,
    MarketplaceServiceProvider::class,
    InsightsServiceProvider::class,
];
