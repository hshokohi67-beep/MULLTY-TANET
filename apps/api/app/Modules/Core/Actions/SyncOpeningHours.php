<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a branch's weekly schedule atomically.
 */
final class SyncOpeningHours
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{weekday: int, opens_at: string, closes_at: string}>  $intervals
     */
    public function handle(Branch $branch, array $intervals): Branch
    {
        DB::transaction(function () use ($branch, $intervals): void {
            BranchOpeningHour::query()->where('branch_id', $branch->getKey())->delete();

            foreach ($intervals as $interval) {
                BranchOpeningHour::query()->create([
                    'branch_id' => $branch->getKey(),
                    'weekday' => $interval['weekday'],
                    'opens_at' => $interval['opens_at'],
                    'closes_at' => $interval['closes_at'],
                ]);
            }

            $this->audit->record('branch.opening_hours.updated', $branch, ['intervals' => $intervals]);
        });

        return $branch->load('openingHours');
    }
}
