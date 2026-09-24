<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'is_system' => $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->isOwner()
                ? PermissionCatalog::keys()
                : $this->permissions->pluck('key')->values()),
        ];
    }
}
