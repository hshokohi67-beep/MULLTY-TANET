<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\TeamRuleException;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Adds a staff member to the current tenant. Creates the platform user if it doesn't exist yet
 * (identified by mobile number). Existing users keep their password.
 */
final class AddTeamMember
{
    public function __construct(
        private readonly UpdateMemberRoles $updateRoles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $roleIds
     */
    public function handle(User $actor, string $name, string $phoneE164, ?string $initialPassword, array $roleIds): TenantUser
    {
        return DB::transaction(function () use ($actor, $name, $phoneE164, $initialPassword, $roleIds): TenantUser {
            $user = User::query()->where('phone_e164', $phoneE164)->first()
                ?? User::query()->create([
                    'name' => $name,
                    'phone_e164' => $phoneE164,
                    'password' => $initialPassword ?? bin2hex(random_bytes(24)),
                ]);

            if (TenantUser::query()->where('user_id', $user->getKey())->exists()) {
                throw TeamRuleException::alreadyMember();
            }

            $member = TenantUser::query()->create(['user_id' => $user->getKey(), 'status' => TenantUser::STATUS_ACTIVE]);
            $this->updateRoles->handle($actor, $member, $roleIds, audit: false);

            $this->audit->record('team.member_added', $member, ['user_id' => $user->getKey(), 'role_ids' => $roleIds]);

            return $member->load('user', 'roles');
        });
    }
}
