<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Data\CreateTenantData;
use App\Modules\Core\Enums\DomainType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantBranding;
use App\Modules\Core\Models\TenantDomain;
use App\Modules\Identity\Actions\CreateDefaultRoles;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Provisions a new tenant: tenant row, branding, first branch, subdomain,
 * default roles and the owner membership, all in one transaction.
 */
final class CreateTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateDefaultRoles $createDefaultRoles,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(CreateTenantData $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'status' => TenantStatus::Trial,
            ]);

            $owner = $this->findOrCreateOwner($data);

            $this->context->runAs($tenant, function () use ($tenant, $data, $owner): void {
                TenantBranding::query()->create(['seo_title' => $tenant->name]);

                Branch::query()->create(['name' => $data->firstBranchName, 'slug' => 'main']);

                if ($data->subdomainBase) {
                    TenantDomain::query()->create([
                        'domain' => $tenant->slug.'.'.$data->subdomainBase,
                        'type' => DomainType::Subdomain,
                        'is_primary' => true,
                        'verified_at' => now(),
                    ]);
                }

                $roles = $this->createDefaultRoles->handle();

                $membership = TenantUser::query()->create(['user_id' => $owner->getKey(), 'status' => TenantUser::STATUS_ACTIVE]);
                $membership->roles()->attach($roles[Role::OWNER]->getKey(), ['tenant_id' => $tenant->getKey()]);

                $this->audit->record('tenant.created', $tenant, ['name' => $tenant->name, 'slug' => $tenant->slug, 'owner_id' => $owner->getKey()]);
            });

            return $tenant;
        });
    }

    private function findOrCreateOwner(CreateTenantData $data): User
    {
        $existing = User::query()
            ->when($data->ownerEmail, fn ($q) => $q->where('email', $data->ownerEmail))
            ->when(! $data->ownerEmail && $data->ownerPhoneE164, fn ($q) => $q->where('phone_e164', $data->ownerPhoneE164))
            ->first();

        return $existing ?? User::query()->create([
            'name' => $data->ownerName,
            'email' => $data->ownerEmail,
            'phone_e164' => $data->ownerPhoneE164,
            'password' => $data->ownerPassword,
        ]);
    }
}
