<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantUser */
final class TeamMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'user' => new StaffUserResource($this->whenLoaded('user')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
