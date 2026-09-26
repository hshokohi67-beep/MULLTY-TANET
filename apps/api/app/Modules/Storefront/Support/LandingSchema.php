<?php

namespace App\Modules\Storefront\Support;

use Illuminate\Validation\Rule;

/**
 * Everything a café can set on its landing page, in one place: the look options (so two cafés
 * rarely look alike), the words of each section, and the section order. The API stores only
 * values listed here; the web renders them as plain text.
 */
final class LandingSchema
{
    /** Look options => allowed values (the first one is the default). */
    public const DESIGN = [
        'template' => ['night', 'bright', 'warm', 'bold'],
        'font' => ['vazirmatn', 'samim'],
        'type' => ['light', 'bold'],
        'hero' => ['cover', 'center', 'split', 'poster'],
        'texture' => ['glow', 'none', 'grain', 'dots', 'art'],
        'corners' => ['soft', 'sharp', 'round'],
        'motion' => ['subtle', 'none', 'lively'],
    ];

    /** Sections below the hero, in their default order. */
    public const SECTIONS = ['story', 'highlights', 'featured', 'marquee', 'gallery', 'visit'];

    /** Layout variants per section (the first one is the default). */
    public const VARIANTS = [
        'story' => ['photo_end', 'photo_start', 'text'],
        'highlights' => ['bento', 'row', 'numbers'],
        'featured' => ['showcase', 'grid', 'carousel'],
        'marquee' => ['outline', 'solid'],
        'gallery' => ['masonry', 'strip'],
        'visit' => ['cards', 'compact'],
    ];

    public const MAX_HIGHLIGHTS = 4;

    public const MAX_FEATURED = 6;

    public const MAX_PHRASES = 4;

    /** @return array<string, string> */
    public static function defaultDesign(): array
    {
        return array_map(fn (array $values) => $values[0], self::DESIGN);
    }

    /** @return array<string, mixed> */
    public static function defaultContent(string $name): array
    {
        return [
            'hero' => ['eyebrow' => null, 'title' => $name, 'subtitle' => null, 'cta_label' => 'منو و سفارش'],
            'story' => ['variant' => 'photo_end', 'title' => 'داستان ما', 'text' => null],
            'highlights' => ['variant' => 'bento', 'title' => null, 'items' => []],
            'featured' => ['variant' => 'showcase', 'title' => 'پیشنهاد ما', 'product_ids' => []],
            'marquee' => ['variant' => 'outline', 'phrases' => []],
            'gallery' => ['variant' => 'masonry', 'title' => 'گالری'],
            'visit' => ['variant' => 'cards', 'title' => 'به ما سر بزنید'],
        ];
    }

    /** @return list<array{key: string, visible: bool}> */
    public static function defaultSections(): array
    {
        return array_map(fn (string $key) => ['key' => $key, 'visible' => $key !== 'marquee'], self::SECTIONS);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $rules = [
            'is_published' => ['sometimes', 'boolean'],
            'design' => ['required', 'array:'.implode(',', array_keys(self::DESIGN))],
            'content' => ['required', 'array:hero,'.implode(',', self::SECTIONS)],
            'sections' => ['required', 'array', 'size:'.count(self::SECTIONS)],
            'sections.*' => ['required', 'array:key,visible'],
            'sections.*.key' => ['required', 'distinct', Rule::in(self::SECTIONS)],
            'sections.*.visible' => ['required', 'boolean'],

            'content.hero' => ['required', 'array:eyebrow,title,subtitle,cta_label'],
            'content.hero.eyebrow' => ['nullable', 'string', 'max:40'],
            'content.hero.title' => ['required', 'string', 'max:80'],
            'content.hero.subtitle' => ['nullable', 'string', 'max:220'],
            'content.hero.cta_label' => ['required', 'string', 'max:24'],

            'content.story.title' => ['nullable', 'string', 'max:80'],
            'content.story.text' => ['nullable', 'string', 'max:1500'],

            'content.highlights.title' => ['nullable', 'string', 'max:80'],
            'content.highlights.items' => ['present', 'array', 'max:'.self::MAX_HIGHLIGHTS],
            'content.highlights.items.*' => ['array:value,unit,label'],
            'content.highlights.items.*.value' => ['required', 'string', 'max:12'],
            'content.highlights.items.*.unit' => ['nullable', 'string', 'max:12'],
            'content.highlights.items.*.label' => ['required', 'string', 'max:80'],

            'content.featured.title' => ['nullable', 'string', 'max:80'],
            'content.featured.product_ids' => ['present', 'array', 'max:'.self::MAX_FEATURED],
            'content.featured.product_ids.*' => ['string', 'size:26', 'distinct'],

            'content.marquee.phrases' => ['present', 'array', 'max:'.self::MAX_PHRASES],
            'content.marquee.phrases.*' => ['nullable', 'string', 'max:40'],

            'content.gallery.title' => ['nullable', 'string', 'max:80'],
            'content.visit.title' => ['nullable', 'string', 'max:80'],
        ];

        foreach (self::DESIGN as $key => $values) {
            $rules["design.$key"] = ['required', Rule::in($values)];
        }
        foreach (self::VARIANTS as $section => $values) {
            $rules["content.$section"] = ['required', 'array'];
            $rules["content.$section.variant"] = ['required', Rule::in($values)];
        }

        return $rules;
    }

    /**
     * Keeps only known keys, trims text and turns blank optional text into null.
     *
     * @param  array<string, mixed>  $content  validated content
     * @return array<string, mixed>
     */
    public static function cleanContent(array $content, string $name): array
    {
        $defaults = self::defaultContent($name);
        $text = fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $out = [];

        foreach ($defaults as $section => $fields) {
            $given = is_array($content[$section] ?? null) ? $content[$section] : [];
            foreach ($fields as $field => $default) {
                $value = $given[$field] ?? $default;
                $out[$section][$field] = match (true) {
                    $field === 'items' => array_values(array_map(fn (array $i) => [
                        'value' => (string) $text($i['value'] ?? null),
                        'unit' => $text($i['unit'] ?? null),
                        'label' => (string) $text($i['label'] ?? null),
                    ], is_array($value) ? $value : [])),
                    $field === 'phrases' => array_values(array_filter(array_map($text, is_array($value) ? $value : []))),
                    $field === 'product_ids' => array_values(array_unique(array_filter(is_array($value) ? $value : [], 'is_string'))),
                    default => $text($value),
                };
            }
        }
        $out['hero']['title'] ??= $name;
        $out['hero']['cta_label'] ??= 'منو و سفارش';

        return $out;
    }

    /**
     * A stored config merged over today's defaults, so options added later still have a value.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, string>
     */
    public static function mergeDesign(array $design): array
    {
        $out = self::defaultDesign();
        foreach (self::DESIGN as $key => $values) {
            if (isset($design[$key]) && in_array($design[$key], $values, true)) {
                $out[$key] = $design[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return list<array{key: string, visible: bool}>
     */
    public static function mergeSections(array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            if (is_array($s) && in_array($s['key'] ?? null, self::SECTIONS, true) && ! isset($out[$s['key']])) {
                $out[$s['key']] = ['key' => (string) $s['key'], 'visible' => (bool) ($s['visible'] ?? true)];
            }
        }
        foreach (self::SECTIONS as $key) {
            $out[$key] ??= ['key' => $key, 'visible' => false];
        }

        return array_values($out);
    }
}
