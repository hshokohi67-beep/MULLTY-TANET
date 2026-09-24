<?php

namespace App\Modules\Kitchen\Actions;

use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Pairing replaces the legacy shared PIN. A manager creates a device and reads out a 6-digit code
 * (valid 10 minutes, single use, stored only as a keyed hash); the tablet exchanges it for its own
 * token. Revoking a device kills its token immediately.
 */
final class KitchenDevices
{
    public const CODE_TTL_MINUTES = 10;

    /**
     * @param  array{branch_id: string, station_id?: ?string, name: string}  $data
     * @return array{device: KitchenDevice, code: string}
     */
    public function create(array $data): array
    {
        $device = KitchenDevice::query()->create($data);
        app(AuditLogger::class)->record('kds.device_created', $device, ['name' => $device->name]);

        return ['device' => $device, 'code' => $this->issueCode($device)];
    }

    /** A new code for re-pairing (e.g. a replaced tablet). Existing tokens stop working. */
    public function repair(KitchenDevice $device): string
    {
        $device->tokens()->delete();
        $device->forceFill(['revoked_at' => null, 'paired_at' => null])->save();
        app(AuditLogger::class)->record('kds.device_repaired', $device);

        return $this->issueCode($device);
    }

    public function revoke(KitchenDevice $device): void
    {
        $device->tokens()->delete();
        $device->forceFill(['revoked_at' => now(), 'pairing_code_hash' => null, 'pairing_expires_at' => null])->save();
        app(AuditLogger::class)->record('kds.device_revoked', $device);
    }

    /** @return array{device: KitchenDevice, token: string} */
    public function pair(string $code): array
    {
        return DB::transaction(function () use ($code): array {
            $device = KitchenDevice::query()
                ->where('pairing_code_hash', self::hash($code))
                ->where('pairing_expires_at', '>', now())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first() ?? throw KitchenException::pairingCodeInvalid();

            $device->forceFill(['pairing_code_hash' => null, 'pairing_expires_at' => null, 'paired_at' => now(), 'last_seen_at' => now()])->save();
            $token = $device->createToken('kds', ['kds'])->plainTextToken;
            app(AuditLogger::class)->record('kds.device_paired', $device, null, $device);

            return ['device' => $device, 'token' => $token];
        });
    }

    private function issueCode(KitchenDevice $device): string
    {
        // Collisions within one tenant are astronomically unlikely in a 10-minute window, but retry anyway.
        for ($attempt = 0; ; $attempt++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            $taken = KitchenDevice::query()->where('pairing_code_hash', self::hash($code))->where('pairing_expires_at', '>', now())->exists();

            if (! $taken || $attempt >= 5) {
                break;
            }
        }

        $device->forceFill(['pairing_code_hash' => self::hash($code), 'pairing_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)])->save();

        return $code;
    }

    /** Keyed hash: a leaked database row can't be brute-forced offline in a million tries. */
    private static function hash(string $code): string
    {
        return hash_hmac('sha256', 'kds-pairing|'.$code, (string) config('app.key'));
    }
}
