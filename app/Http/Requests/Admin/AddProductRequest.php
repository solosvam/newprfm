<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($this->route('id') ?? $this->route('product')),
            ],

            'brand_id' => [
                'required',
                'exists:brands,id',
            ],

            'type_id' => [
                'required',
                'exists:types,id',
            ],

            'old_id' => [
                'nullable',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'categories' => [
                'required',
                'array',
                'min:1',
            ],

            'categories.*' => [
                'required',
                'distinct',
                'exists:categories,id',
            ],

            'genders' => [
                'required',
                'array',
                'min:1',
            ],

            'genders.*' => [
                'required',
                'distinct',
                'exists:genders,id',
            ],

            'ingredients' => [
                'nullable',
                'array',
            ],

            'ingredients.*' => [
                'required',
                'distinct',
                'exists:ingredients,id',
            ],

            'content_az' => [
                'required',
                'string',
            ],

            'content_en' => [
                'nullable',
                'string',
            ],

            'content_ru' => [
                'nullable',
                'string',
            ],

            'variants' => [
                'required',
                'array',
                'min:1',
            ],

            'variants.*.size_id' => [
                'required',
                'distinct',
                'exists:sizes,id',
            ],

            'variants.*.price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'variants.*.active' => [
                'nullable',
                'boolean',
            ],

            'images' => [
                'nullable',
                'array',
            ],

            'images.*' => [
                'image',
                'max:10240',
            ],

            'remote_image_ids' => [
                'nullable',
                'array',
                'max:5',
            ],

            'remote_image_ids.*' => [
                'integer',
                'distinct',
                'min:0',
            ],

            'remote_primary_image_id' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'image_order' => [
                'nullable',
                'array',
            ],

            'image_order.*' => [
                'integer',
                'distinct',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug yalnız kiçik latın hərfləri, rəqəmlər və tire ilə yazılmalıdır.',
            'slug.unique' => 'Bu slug artıq başqa məhsulda istifadə olunur.',

            'brand_id.required' => 'Brend seçilməlidir.',
            'brand_id.exists' => 'Seçilən brend mövcud deyil.',

            'type_id.required' => 'Məhsul tipi seçilməlidir.',
            'type_id.exists' => 'Seçilən məhsul tipi mövcud deyil.',

            'name.required' => 'Ətir adı mütləqdir.',
            'name.string' => 'Ətir adı mətn formatında olmalıdır.',
            'name.max' => 'Ətir adı 255 simvoldan çox olmamalıdır.',

            'categories.required' => 'Kateqoriya seçilməlidir.',
            'categories.array' => 'Kateqoriya düzgün formatda deyil.',
            'categories.min' => 'Ən azı bir kateqoriya seçilməlidir.',
            'categories.*.distinct' => 'Eyni kateqoriya bir neçə dəfə seçilə bilməz.',
            'categories.*.exists' => 'Seçilən kateqoriya mövcud deyil.',

            'genders.required' => 'Cinsiyyət seçilməlidir.',
            'genders.array' => 'Cinsiyyət düzgün formatda deyil.',
            'genders.min' => 'Ən azı bir cinsiyyət seçilməlidir.',
            'genders.*.distinct' => 'Eyni cinsiyyət bir neçə dəfə seçilə bilməz.',
            'genders.*.exists' => 'Seçilən cinsiyyət mövcud deyil.',

            'ingredients.array' => 'İnqrediyentlər düzgün formatda deyil.',
            'ingredients.*.distinct' => 'Eyni inqrediyent bir neçə dəfə seçilə bilməz.',
            'ingredients.*.exists' => 'Seçilən inqrediyent mövcud deyil.',

            'content_az.required' => 'İnformasiya AZ sahəsi mütləqdir.',
            'content_az.string' => 'İnformasiya AZ mətn formatında olmalıdır.',
            'content_en.string' => 'İnformasiya EN mətn formatında olmalıdır.',
            'content_ru.string' => 'İnformasiya RU mətn formatında olmalıdır.',

            'variants.required' => 'Ən azı bir variant əlavə edilməlidir.',
            'variants.array' => 'Variantlar düzgün formatda deyil.',
            'variants.min' => 'Ən azı bir variant əlavə edilməlidir.',

            'variants.*.size_id.required' => 'Variant üçün ölçü seçilməlidir.',
            'variants.*.size_id.distinct' => 'Eyni ölçü bir neçə dəfə əlavə edilə bilməz.',
            'variants.*.size_id.exists' => 'Seçilən ölçü mövcud deyil.',

            'variants.*.price.required' => 'Hər variant üçün qiymət qeyd olunmalıdır.',
            'variants.*.price.numeric' => 'Qiymət rəqəm formatında olmalıdır.',
            'variants.*.price.min' => 'Qiymət sıfırdan aşağı ola bilməz.',

            'variants.*.active.boolean' => 'Variantın aktiv statusu düzgün deyil.',

            'images.array' => 'Şəkillər düzgün formatda deyil.',
            'images.*.image' => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'images.*.max' => 'Şəklin həcmi maksimum 10 MB ola bilər.',

            'remote_image_ids.max' => 'Ən çox 5 şəkil seçə bilərsiniz.',
            'image_order.*.distinct' => 'Eyni şəkil sıralamada bir neçə dəfə ola bilməz.',
        ];
    }
}
