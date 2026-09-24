<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SaveModifierGroup
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, min_select: int, max_select: int, sort?: int}  $group
     * @param  list<array{id?: ?string, name: string, price_delta: int, is_default?: bool, is_active?: bool}>  $modifiers
     */
    public function handle(array $group, array $modifiers, ?ModifierGroup $model = null): ModifierGroup
    {
        $min = $group['min_select'];
        $max = $group['max_select'];

        if ($max !== 0 && $min > $max) {
            throw CatalogRuleException::invalidSelectionRange();
        }

        $defaults = count(array_filter($modifiers, fn (array $m) => (bool) ($m['is_default'] ?? false)));

        if ($max !== 0 && $defaults > $max) {
            throw CatalogRuleException::tooManyDefaults();
        }

        return DB::transaction(function () use ($group, $modifiers, $model): ModifierGroup {
            $model ??= new ModifierGroup;
            $isNew = ! $model->exists;
            $model->fill(['name' => $group['name'], 'min_select' => $group['min_select'], 'max_select' => $group['max_select'], 'sort' => $group['sort'] ?? 0]);
            $model->save();

            $keep = [];

            foreach ($modifiers as $index => $data) {
                $modifier = ! empty($data['id'])
                    ? Modifier::query()->where('modifier_group_id', $model->getKey())->findOrFail($data['id'])
                    : new Modifier(['modifier_group_id' => $model->getKey()]);

                $modifier->fill([
                    'name' => $data['name'],
                    'price_delta' => $data['price_delta'],
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'sort' => $index,
                ])->save();

                $keep[] = $modifier->getKey();
            }

            Modifier::query()->where('modifier_group_id', $model->getKey())->whereNotIn('id', $keep)->delete();

            $this->audit->record($isNew ? 'modifier_group.created' : 'modifier_group.updated', $model, ['name' => $model->name, 'modifiers' => count($keep)]);

            return $model->load('modifiers');
        });
    }
}
