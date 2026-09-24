<?php

namespace App\Modules\Storefront\Http\Requests;

use App\Modules\Storefront\Models\Story;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/** Create (multipart, image required) or update (image optional) a story. */
final class StoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Multipart sends booleans as strings.
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
        foreach (['branch_id', 'link_target', 'caption', 'cta_label', 'starts_at', 'ends_at'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->route('story') === null;

        return [
            'image' => [
                $creating ? 'required' : 'sometimes',
                // Raster only (SVG can carry scripts); the file is re-encoded to WebP anyway.
                File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(8 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(400)->minHeight(400)->maxWidth(8000)->maxHeight(8000)),
            ],
            'caption' => ['nullable', 'string', 'max:200'],
            'link_type' => ['sometimes', Rule::in(Story::LINK_TYPES)],
            'link_target' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:30'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
            'branch_id' => ['nullable', 'string', TenantExists::in('branches')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** The link target must fit its type: a product/category of this tenant, or an https URL. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $type = $this->input('link_type', 'none');
            $target = $this->input('link_target');

            if ($type === 'none' || $v->errors()->has('link_type')) {
                return;
            }

            $rule = match ($type) {
                'product' => ['required', 'string', TenantExists::in('products', withoutTrashed: true)],
                'category' => ['required', 'string', TenantExists::in('categories')],
                default => ['required', 'url:https', 'max:500'],
            };

            $check = ValidatorFacade::make(['link_target' => $target], ['link_target' => $rule]);
            if ($check->fails()) {
                $v->errors()->add('link_target', $type === 'url' ? 'لینک باید یک آدرس کامل با https باشد.' : 'مقصد لینک را انتخاب کنید.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return ['image' => 'تصویر', 'caption' => 'متن', 'cta_label' => 'متن دکمه', 'ends_at' => 'زمان پایان'];
    }
}
