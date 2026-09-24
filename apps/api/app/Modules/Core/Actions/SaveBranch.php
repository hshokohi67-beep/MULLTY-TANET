<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Data\BranchData;
use App\Modules\Core\Exceptions\LastActiveBranchException;
use App\Modules\Core\Models\Branch;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SaveBranch
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(BranchData $data, ?Branch $branch = null): Branch
    {
        return DB::transaction(function () use ($data, $branch): Branch {
            $branch ??= new Branch;
            $isNew = ! $branch->exists;

            $branch->fill($data->toAttributes());

            if (! $isNew && $branch->isDirty('is_active') && ! $branch->is_active) {
                $otherActive = Branch::query()->whereKeyNot($branch->getKey())->where('is_active', true)->lockForUpdate()->exists();

                if (! $otherActive) {
                    throw new LastActiveBranchException;
                }
            }

            $changes = $branch->getDirty();
            $branch->save();

            $this->audit->record($isNew ? 'branch.created' : 'branch.updated', $branch, $changes);

            return $branch;
        });
    }
}
