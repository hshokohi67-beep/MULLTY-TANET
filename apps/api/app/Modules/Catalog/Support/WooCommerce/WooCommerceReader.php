<?php

namespace App\Modules\Catalog\Support\WooCommerce;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Read-only access to a WooCommerce database (classic posts/postmeta product storage,
 * which WooCommerce still uses for products even with HPOS orders).
 * Never writes to the source database.
 */
final class WooCommerceReader
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $prefix = 'wp_',
    ) {}

    /**
     * @return Collection<int, \stdClass>
     */
    public function categories(): Collection
    {
        return $this->db->table($this->prefix.'terms as t')
            ->join($this->prefix.'term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'product_cat')
            ->orderBy('tt.parent')->orderBy('t.term_id')
            ->get(['t.term_id', 't.name', 't.slug', 'tt.parent', 'tt.description']);
    }

    /**
     * Published/private/draft products (trash and auto-drafts are ignored).
     *
     * @return Collection<int, \stdClass>
     */
    public function products(): Collection
    {
        return $this->db->table($this->prefix.'posts')
            ->where('post_type', 'product')
            ->whereIn('post_status', ['publish', 'private', 'draft'])
            ->orderBy('menu_order')->orderBy('ID')
            ->get(['ID', 'post_title', 'post_content', 'post_excerpt', 'menu_order', 'post_status']);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function variations(int $productId): Collection
    {
        return $this->db->table($this->prefix.'posts')
            ->where('post_type', 'product_variation')
            ->where('post_parent', $productId)
            ->whereIn('post_status', ['publish', 'private'])
            ->orderBy('menu_order')->orderBy('ID')
            ->get(['ID', 'post_title', 'menu_order', 'post_status']);
    }

    /** @return array<string, string> meta_key => meta_value (first value wins) */
    public function meta(int $postId): array
    {
        $meta = [];

        foreach ($this->db->table($this->prefix.'postmeta')->where('post_id', $postId)->orderBy('meta_id')->get(['meta_key', 'meta_value']) as $row) {
            $meta[$row->meta_key] ??= (string) $row->meta_value;
        }

        return $meta;
    }

    /**
     * Term slugs (and ids) the post has in the given taxonomy.
     *
     * @return Collection<int, \stdClass>
     */
    public function terms(int $postId, string $taxonomy): Collection
    {
        return $this->db->table($this->prefix.'term_relationships as tr')
            ->join($this->prefix.'term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join($this->prefix.'terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $postId)
            ->where('tt.taxonomy', $taxonomy)
            ->get(['t.term_id', 't.slug', 't.name']);
    }

    /** Human name of an attribute term (e.g. pa_size "large" → «بزرگ»). */
    public function attributeTermName(string $taxonomy, string $slug): ?string
    {
        return $this->db->table($this->prefix.'terms as t')
            ->join($this->prefix.'term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', $taxonomy)
            ->where('t.slug', $slug)
            ->value('t.name');
    }

    public function attachmentUrl(int $attachmentId): ?string
    {
        return $this->db->table($this->prefix.'posts')->where('ID', $attachmentId)->where('post_type', 'attachment')->value('guid');
    }

    /**
     * WooCommerce stores upsells as a serialized PHP array. Objects are never instantiated.
     *
     * @return list<int>
     */
    public static function parseIdList(?string $serialized): array
    {
        if ($serialized === null || $serialized === '') {
            return [];
        }

        $value = @unserialize($serialized, ['allowed_classes' => false]);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value), fn (int $id) => $id > 0));
    }
}
