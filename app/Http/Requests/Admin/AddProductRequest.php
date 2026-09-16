<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AddProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'brand_id' => 'required|exists:brands,id',
            'category' => 'required|array',
            'category.*' => 'exists:categories,id',
            'gender' => 'required|array',
            'gender.*' => 'in:0,1,2',
            'name' => 'required|string|max:255',
            'type_id' => 'required|exists:types,id',
            'ingredients' => 'required|array',
            'ingredients.*' => 'exists:ingredients,id',
            'content_az' => 'required|string',
            'content_en' => 'nullable|string',
            'content_ru' => 'nullable|string',
            'size' => 'required|array',
            'size.*' => 'exists:sizes,id',
            'size_price' => 'required|array',
            'size_price.*' => 'required|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'brand_id.required' => 'Brend seçilməlidir.',
            'brand_id.exists' => 'Seçilən brend mövcud deyil.',
            'category.required' => 'Kateqoriya seçilməlidir.',
            'category.array' => 'Kateqoriya düzgün formatda deyil.',
            'category.*.exists' => 'Seçilən kateqoriya mövcud deyil.',
            'gender.required' => 'Cinsiyyət seçilməlidir.',
            'gender.array' => 'Cinsiyyət düzgün formatda deyil.',
            'gender.*.in' => 'Seçilən cinsiyyət dəyəri düzgün deyil.',
            'name.required' => 'Ətir adı mütləqdir.',
            'name.string' => 'Ətir adı mətn formatında olmalıdır.',
            'name.max' => 'Ətir adı 255 simvoldan çox olmamalıdır.',
            'type_id.required' => 'Məhsul tipi seçilməlidir.',
            'type_id.exists' => 'Seçilən məhsul tipi mövcud deyil.',
            'ingredients.required' => 'İnqredientlər seçilməlidir.',
            'ingredients.array' => 'İnqredientlər düzgün formatda deyil.',
            'ingredients.*.exists' => 'Seçilən inqredient mövcud deyil.',
            'content_az.required' => 'İnformasiya AZ sahəsi mütləqdir.',
            'content_az.string' => 'İnformasiya AZ mətn formatında olmalıdır.',
            'content_en.string' => 'İnformasiya EN mətn formatında olmalıdır.',
            'content_ru.string' => 'İnformasiya RU mətn formatında olmalıdır.',
            'size.required' => 'Ölçü seçilməlidir.',
            'size.array' => 'Ölçü düzgün formatda deyil.',
            'size.*.exists' => 'Seçilən ölçü mövcud deyil.',
            'size_price.required' => 'Ölçü qiyməti mütləqdir.',
            'size_price.array' => 'Ölçü qiyməti düzgün formatda deyil.',
            'size_price.*.required' => 'Hər ölçü üçün qiymət qeyd olunmalıdır.',
            'size_price.*.numeric' => 'Ölçü qiyməti rəqəm formatında olmalıdır.',
            'size_price.*.min' => 'Ölçü qiyməti sıfırdan aşağı ola bilməz.',
        ];
    }
}
