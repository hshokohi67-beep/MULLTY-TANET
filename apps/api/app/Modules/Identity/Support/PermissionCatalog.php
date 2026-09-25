<?php

namespace App\Modules\Identity\Support;

/**
 * Every permission in the system, defined in code (the single source of truth).
 * `php artisan permissions:sync` mirrors this into the `permissions` table.
 * Later modules append their own permissions here.
 */
final class PermissionCatalog
{
    public const TENANT_VIEW = 'tenant.view';

    public const TENANT_UPDATE = 'tenant.update';

    public const BRANDING_UPDATE = 'branding.update';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_UPDATE = 'settings.update';

    public const BRANCHES_VIEW = 'branches.view';

    public const BRANCHES_MANAGE = 'branches.manage';

    public const TEAM_VIEW = 'team.view';

    public const TEAM_MANAGE = 'team.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_MANAGE = 'catalog.manage';

    public const PRICES_MANAGE = 'prices.manage';

    public const AVAILABILITY_MANAGE = 'availability.manage';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const ORDERS_CREATE = 'orders.create';

    public const TABLES_MANAGE = 'tables.manage';

    public const DELIVERY_MANAGE = 'delivery.manage';

    public const DISCOUNTS_MANAGE = 'discounts.manage';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_RECORD = 'payments.record';

    public const PAYMENTS_REFUND = 'payments.refund';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    public const CUSTOMERS_EXPORT = 'customers.export';

    public const WALLET_ADJUST = 'wallet.adjust';

    public const LOYALTY_MANAGE = 'loyalty.manage';

    public const KDS_OPERATE = 'kds.operate';

    public const KDS_MANAGE = 'kds.manage';

    public const STOREFRONT_MANAGE = 'storefront.manage';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_MANAGE = 'inventory.manage';

    public const PURCHASING_MANAGE = 'purchasing.manage';

    public const EXPENSES_MANAGE = 'expenses.manage';

    public const STAFF_MANAGE = 'staff.manage';

    public const ATTENDANCE_SELF = 'attendance.self';

    /**
     * key => [group, Persian label]
     *
     * @return array<string, array{group: string, label: string}>
     */
    public static function all(): array
    {
        return [
            self::TENANT_VIEW => ['group' => 'tenant', 'label' => 'مشاهده اطلاعات کسب‌وکار'],
            self::TENANT_UPDATE => ['group' => 'tenant', 'label' => 'ویرایش اطلاعات کسب‌وکار'],
            self::BRANDING_UPDATE => ['group' => 'tenant', 'label' => 'ویرایش برند و سئو'],
            self::SETTINGS_VIEW => ['group' => 'settings', 'label' => 'مشاهده تنظیمات'],
            self::SETTINGS_UPDATE => ['group' => 'settings', 'label' => 'ویرایش تنظیمات'],
            self::BRANCHES_VIEW => ['group' => 'branches', 'label' => 'مشاهده شعبه‌ها'],
            self::BRANCHES_MANAGE => ['group' => 'branches', 'label' => 'مدیریت شعبه‌ها و ساعات کاری'],
            self::TEAM_VIEW => ['group' => 'team', 'label' => 'مشاهده اعضای تیم'],
            self::TEAM_MANAGE => ['group' => 'team', 'label' => 'مدیریت اعضای تیم و نقش‌ها'],
            self::AUDIT_VIEW => ['group' => 'audit', 'label' => 'مشاهده گزارش رویدادها'],
            self::CATALOG_VIEW => ['group' => 'catalog', 'label' => 'مشاهده منو'],
            self::CATALOG_MANAGE => ['group' => 'catalog', 'label' => 'مدیریت منو، دسته‌بندی‌ها و افزودنی‌ها'],
            self::PRICES_MANAGE => ['group' => 'catalog', 'label' => 'تغییر قیمت‌ها (تکی و گروهی)'],
            self::AVAILABILITY_MANAGE => ['group' => 'catalog', 'label' => 'اعلام «تمام شد» برای آیتم‌ها'],
            self::ORDERS_VIEW => ['group' => 'orders', 'label' => 'مشاهده سفارش‌ها'],
            self::ORDERS_MANAGE => ['group' => 'orders', 'label' => 'تغییر وضعیت سفارش‌ها و پاسخ به درخواست میزها'],
            self::ORDERS_CREATE => ['group' => 'orders', 'label' => 'ثبت سفارش از پنل (پیشخوان/تلفنی)'],
            self::TABLES_MANAGE => ['group' => 'orders', 'label' => 'مدیریت میزها و کدهای QR'],
            self::DELIVERY_MANAGE => ['group' => 'orders', 'label' => 'مدیریت محدوده‌های ارسال'],
            self::DISCOUNTS_MANAGE => ['group' => 'orders', 'label' => 'مدیریت تخفیف‌ها و کدهای تخفیف'],
            self::PAYMENTS_VIEW => ['group' => 'payments', 'label' => 'مشاهده پرداخت‌ها'],
            self::PAYMENTS_RECORD => ['group' => 'payments', 'label' => 'ثبت پرداخت نقدی و کارتخوان'],
            self::PAYMENTS_REFUND => ['group' => 'payments', 'label' => 'ثبت بازگشت وجه'],
            self::CUSTOMERS_VIEW => ['group' => 'customers', 'label' => 'مشاهده مشتریان، کیف پول و امتیاز'],
            self::CUSTOMERS_MANAGE => ['group' => 'customers', 'label' => 'ویرایش اطلاعات مشتریان'],
            self::CUSTOMERS_EXPORT => ['group' => 'customers', 'label' => 'خروجی فهرست مشتریان (شامل شماره موبایل)'],
            self::WALLET_ADJUST => ['group' => 'customers', 'label' => 'افزایش/کاهش دستی کیف پول و امتیاز'],
            self::LOYALTY_MANAGE => ['group' => 'customers', 'label' => 'مدیریت باشگاه مشتریان (سطح‌ها، کش‌بک، تنظیمات)'],
            self::KDS_OPERATE => ['group' => 'kitchen', 'label' => 'کار با نمایشگر آشپزخانه'],
            self::KDS_MANAGE => ['group' => 'kitchen', 'label' => 'مدیریت ایستگاه‌ها و دستگاه‌های آشپزخانه'],
            self::STOREFRONT_MANAGE => ['group' => 'tenant', 'label' => 'مدیریت استوری‌ها و ظاهر فروشگاه آنلاین'],
            self::INVENTORY_VIEW => ['group' => 'inventory', 'label' => 'مشاهده‌ی انبار، موجودی و دستور پخت'],
            self::INVENTORY_MANAGE => ['group' => 'inventory', 'label' => 'مدیریت مواد اولیه، دستور پخت، ضایعات و انبارگردانی'],
            self::PURCHASING_MANAGE => ['group' => 'inventory', 'label' => 'خرید از تأمین‌کننده و پرداخت به او'],
            self::EXPENSES_MANAGE => ['group' => 'operations', 'label' => 'ثبت و مدیریت هزینه‌ها'],
            self::STAFF_MANAGE => ['group' => 'operations', 'label' => 'کارکنان، برنامه‌ی شیفت، حضور و غیاب و حقوق'],
            self::ATTENDANCE_SELF => ['group' => 'operations', 'label' => 'ثبت ورود و خروج خود'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }
}
