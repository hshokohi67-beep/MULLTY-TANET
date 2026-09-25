<?php

namespace Database\Seeders;

use App\Modules\Core\Actions\CreateTenant;
use App\Modules\Core\Actions\SyncOpeningHours;
use App\Modules\Core\Data\CreateTenantData;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Actions\AddTeamMember;
use App\Modules\Identity\Actions\SyncPermissions;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data, in Persian. Never run in production.
 *
 * Logins (password for all: password):
 *   platform admin: admin@example.test
 *   owner of «کافه نمونه»: 09120000001
 *   cashier of «کافه نمونه»: 09120000002
 *   owner of «کافه دوم» (for isolation testing): 09120000003
 */
class DemoSeeder extends Seeder
{
    public function run(
        CreateTenant $createTenant,
        SyncOpeningHours $syncHours,
        AddTeamMember $addMember,
        SyncPermissions $syncPermissions,
        TenantContext $context,
    ): void {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $syncPermissions->handle();

        User::query()->firstOrCreate(['email' => 'admin@example.test'], [
            'name' => 'مدیر پلتفرم',
            'password' => 'password',
        ])->forceFill(['is_platform_admin' => true])->save();

        $cafe = $createTenant->handle(new CreateTenantData(
            name: 'کافه نمونه',
            slug: 'cafe-nemooneh',
            ownerName: 'مالک نمونه',
            ownerEmail: null,
            ownerPhoneE164: '+989120000001',
            ownerPassword: 'password',
            firstBranchName: 'شعبه مرکزی',
            subdomainBase: config('tenancy.subdomain_base'),
        ));
        $cafe->update(['status' => TenantStatus::Active]);

        $context->runAs($cafe, function () use ($syncHours, $addMember): void {
            $main = Branch::query()->where('slug', 'main')->firstOrFail();
            $main->update(['province' => 'تهران', 'city' => 'تهران', 'address' => 'خیابان ولیعصر، کوچه نمونه، پلاک ۱۲', 'phone' => '02188776655', 'latitude' => 35.7219, 'longitude' => 51.4056]);

            // Saturday–Thursday 08:00–23:00, Friday 16:00–01:00 (runs past midnight).
            $hours = [];
            foreach ([6, 7, 1, 2, 3, 4] as $day) {
                $hours[] = ['weekday' => $day, 'opens_at' => '08:00', 'closes_at' => '23:00'];
            }
            $hours[] = ['weekday' => 5, 'opens_at' => '16:00', 'closes_at' => '01:00'];
            $syncHours->handle($main, $hours);

            Branch::query()->create(['name' => 'شعبه ونک', 'slug' => 'vanak', 'province' => 'تهران', 'city' => 'تهران', 'address' => 'میدان ونک، خیابان ملاصدرا']);

            $owner = User::query()->where('phone_e164', '+989120000001')->firstOrFail();
            $cashierRole = Role::query()->where('key', 'cashier')->firstOrFail();
            $addMember->handle($owner, 'صندوق‌دار نمونه', '+989120000002', 'password', [$cashierRole->getKey()]);

            $this->call(CatalogDemoSeeder::class);
            $this->call(CommerceDemoSeeder::class);
            $this->call(LoyaltyDemoSeeder::class);
            $this->call(KitchenDemoSeeder::class);
            $this->call(StorefrontDemoSeeder::class);
            $this->call(InventoryDemoSeeder::class);
            $this->call(OperationsDemoSeeder::class);
        });

        $second = $createTenant->handle(new CreateTenantData(
            name: 'کافه دوم',
            slug: 'cafe-dovom',
            ownerName: 'مالک کافه دوم',
            ownerEmail: null,
            ownerPhoneE164: '+989120000003',
            ownerPassword: 'password',
            firstBranchName: 'شعبه اصلی',
            subdomainBase: config('tenancy.subdomain_base'),
        ));
        $second->update(['status' => TenantStatus::Active]);
    }
}
