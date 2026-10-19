<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\MenuCollection;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Creates or updates a menu collection and its products (in the given order). */
final class SaveMenuCollection
{
    public function __construct(private readonly AuditLogger $audit, private readonly TenantContext $context) {}

    /** @param  array<string, mixed>  $data  validated, with `product_ids` */
    public function handle(array $data, ?MenuCollection $collection = null): MenuCollection
    {
        $collection ??= new MenuCollection;
        $isNew = ! $collection->exists;
        $ids = array_values(array_unique($data['product_ids']));
        unset($data['product_ids']);

        return DB::transaction(function () use ($collection, $data, $ids, $isNew): MenuCollection {
            $collection->fill($data)->save();
            $tenantId = $this->context->require()->getKey();
            $collection->products()->sync(collect($ids)->mapWithKeys(fn (string $id, int $i) => [$id => ['tenant_id' => $tenantId, 'sort' => $i]])->all());
            $this->audit->record($isNew ? 'menu_collection.created' : 'menu_collection.updated', $collection, ['title' => $collection->title]);

            return $collection->load('products:id,name');
        });
    }
}
