<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Support\PermissionCatalog as P;

/**
 * Roles every new tenant starts with. Owners always hold every permission.
 */
final class DefaultRoles
{
    /**
     * @return array<string, array{name: string, permissions: list<string>|'*'}>
     */
    public static function definitions(): array
    {
        return [
            Role::OWNER => ['name' => 'مالک', 'permissions' => '*'],
            'manager' => ['name' => 'مدیر', 'permissions' => [
                P::TENANT_VIEW, P::BRANDING_UPDATE, P::SETTINGS_VIEW,
                P::BRANCHES_VIEW, P::BRANCHES_MANAGE, P::TEAM_VIEW, P::AUDIT_VIEW,
                P::CATALOG_VIEW, P::CATALOG_MANAGE, P::PRICES_MANAGE, P::AVAILABILITY_MANAGE,
                P::ORDERS_VIEW, P::ORDERS_MANAGE, P::ORDERS_CREATE, P::TABLES_MANAGE, P::DELIVERY_MANAGE, P::DISCOUNTS_MANAGE,
                P::PAYMENTS_VIEW, P::PAYMENTS_RECORD, P::PAYMENTS_REFUND,
                P::CUSTOMERS_VIEW, P::CUSTOMERS_MANAGE, P::CUSTOMERS_EXPORT, P::WALLET_ADJUST, P::LOYALTY_MANAGE,
                P::KDS_OPERATE, P::KDS_MANAGE, P::STOREFRONT_MANAGE,
                P::INVENTORY_VIEW, P::INVENTORY_MANAGE, P::PURCHASING_MANAGE,
                P::EXPENSES_MANAGE, P::STAFF_MANAGE, P::ATTENDANCE_SELF, P::REPORTS_VIEW, P::MARKETPLACE_MANAGE, P::ADS_MANAGE,
            ]],
            'cashier' => ['name' => 'صندوق‌دار', 'permissions' => [P::TENANT_VIEW, P::BRANCHES_VIEW, P::CATALOG_VIEW, P::AVAILABILITY_MANAGE, P::ORDERS_VIEW, P::ORDERS_MANAGE, P::ORDERS_CREATE, P::PAYMENTS_VIEW, P::PAYMENTS_RECORD, P::CUSTOMERS_VIEW, P::KDS_OPERATE, P::ATTENDANCE_SELF]],
            'kitchen' => ['name' => 'آشپزخانه', 'permissions' => [P::TENANT_VIEW, P::BRANCHES_VIEW, P::CATALOG_VIEW, P::AVAILABILITY_MANAGE, P::ORDERS_VIEW, P::ORDERS_MANAGE, P::KDS_OPERATE, P::INVENTORY_VIEW, P::ATTENDANCE_SELF]],
            'waiter' => ['name' => 'سالن‌دار', 'permissions' => [P::TENANT_VIEW, P::BRANCHES_VIEW, P::CATALOG_VIEW, P::ORDERS_VIEW, P::ORDERS_MANAGE, P::ORDERS_CREATE, P::KDS_OPERATE, P::ATTENDANCE_SELF]],
        ];
    }
}
