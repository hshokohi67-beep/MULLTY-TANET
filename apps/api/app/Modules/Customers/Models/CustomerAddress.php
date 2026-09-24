<?php

namespace App\Modules\Customers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved delivery address. Orders copy it into an immutable snapshot, so editing or
 * deleting an address never changes past orders.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $title
 * @property ?string $recipient_name
 * @property ?string $recipient_phone_e164
 * @property ?string $province
 * @property string $city
 * @property ?string $district
 * @property string $address
 * @property ?string $postal_code
 * @property ?string $building_number
 * @property ?string $floor
 * @property ?string $unit
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?string $notes
 * @property bool $is_default
 */
#[Fillable([
    'customer_id', 'title', 'recipient_name', 'recipient_phone_e164', 'province', 'city', 'district',
    'address', 'postal_code', 'building_number', 'floor', 'unit', 'latitude', 'longitude', 'notes', 'is_default',
])]
class CustomerAddress extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    public const MAX_PER_CUSTOMER = 10;

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'is_default' => 'boolean'];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** @return array<string, mixed> the frozen copy stored on an order */
    public function snapshot(): array
    {
        return $this->only([
            'id', 'title', 'recipient_name', 'recipient_phone_e164', 'province', 'city', 'district',
            'address', 'postal_code', 'building_number', 'floor', 'unit', 'latitude', 'longitude', 'notes',
        ]);
    }
}
