<?php

namespace App\Modules\Commerce\Actions\Tables;

use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Commerce\Models\TableQrCode;
use App\Support\Audit\AuditLogger;
use App\Support\Security\SecretToken;
use Illuminate\Support\Facades\DB;

/**
 * QR codes are unguessable, revocable tokens (replacing legacy "?table_id=3").
 */
final class ManageTableQr
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Issues a new code and revokes the previous ones (reprinting a table's QR).
     * The raw token is returned exactly once; only its hash is stored.
     *
     * @return array{code: TableQrCode, token: string}
     */
    public function issue(RestaurantTable $table): array
    {
        return DB::transaction(function () use ($table): array {
            TableQrCode::query()->where('table_id', $table->getKey())->where('is_active', true)
                ->update(['is_active' => false, 'revoked_at' => now()]);

            $token = SecretToken::generate();
            $code = TableQrCode::query()->create([
                'table_id' => $table->getKey(),
                'token_hash' => SecretToken::hash($token),
                'token_hint' => substr($token, -4),
                'is_active' => true,
            ]);

            $this->audit->record('table.qr_issued', $table, ['qr_code_id' => $code->getKey()]);

            return ['code' => $code, 'token' => $token];
        });
    }

    /** Resolves a scanned token to an active code on an active table in an active branch. */
    public function resolve(string $token): TableQrCode
    {
        if (! SecretToken::looksValid($token)) {
            throw CommerceException::qrInvalid();
        }

        $code = TableQrCode::query()
            ->with('table.branch')
            ->where('token_hash', SecretToken::hash($token))
            ->where('is_active', true)
            ->first();

        if ($code === null || ! $code->table->is_active || ! $code->table->branch->is_active) {
            throw CommerceException::qrInvalid();
        }

        return $code;
    }
}
