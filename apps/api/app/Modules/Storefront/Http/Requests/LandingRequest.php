<?php

namespace App\Modules\Storefront\Http\Requests;

use App\Modules\Storefront\Support\LandingSchema;
use Illuminate\Foundation\Http\FormRequest;

/** Save the landing page: its look, words and section order (the whole page, every time). */
final class LandingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return LandingSchema::rules();
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'content.hero.title' => 'تیتر اصلی',
            'content.hero.subtitle' => 'متن زیر تیتر',
            'content.hero.cta_label' => 'متن دکمه',
            'content.story.text' => 'متن داستان',
            'content.highlights.items.*.value' => 'عدد',
            'content.highlights.items.*.label' => 'توضیح',
            'content.marquee.phrases.*' => 'عبارت',
        ];
    }
}
