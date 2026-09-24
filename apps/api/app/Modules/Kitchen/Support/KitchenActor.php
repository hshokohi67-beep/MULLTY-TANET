<?php

namespace App\Modules\Kitchen\Support;

use App\Modules\Kitchen\Models\KitchenDevice;
use Illuminate\Http\Request;
use LogicException;

/**
 * Who is using the KDS: a paired device (bound to a branch, maybe a station) or a staff member.
 * Resolved once by the `kds` middleware and stored on the request.
 */
final readonly class KitchenActor
{
    public function __construct(
        public string $type,              // device | user
        public string $id,
        public ?string $branchId = null,  // devices are bound to one branch
        public ?string $stationId = null, // …and optionally one station
    ) {}

    public static function device(KitchenDevice $device): self
    {
        return new self('device', $device->id, $device->branch_id, $device->station_id);
    }

    public static function from(Request $request): self
    {
        $actor = $request->attributes->get('kds_actor');

        return $actor instanceof self ? $actor : throw new LogicException('The kds middleware did not run.');
    }

    public function isDevice(): bool
    {
        return $this->type === 'device';
    }

    /** A device may only touch its own branch (and station); staff may touch any branch of the tenant. */
    public function canReach(string $branchId, ?string $stationId = null): bool
    {
        if ($this->branchId !== null && $this->branchId !== $branchId) {
            return false;
        }

        return $this->stationId === null || $stationId === null || $this->stationId === $stationId;
    }
}
