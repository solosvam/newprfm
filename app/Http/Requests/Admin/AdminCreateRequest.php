<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class AdminCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name'      => 'required|string|min:3|max:20',
            'surname'   => 'required|string|min:3|max:20',
            'mobile'    => 'required|regex:/^[0-9]{12}$/',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6|max:20',
        ];
    }

    public function messages()
    {
        return [
            'name.required'     => 'Ad qeyd olunmalıdır.',
            'name.min'          => 'Ad minimum 3 simvoldan ibarət olmalıdır.',
            'name.max'          => 'Ad maksimum 20 simvoldan ibarət olmalıdır.',
            'surname.required'  => 'Soyad qeyd olunmalıdır.',
            'surname.min'       => 'Soyad minimum 3 simvoldan ibarət olmalıdır.',
            'surname.max'       => 'Soyad maksimum 20 simvoldan ibarət olmalıdır.',
            'mobile.required'   => 'Mobil nömrə qeyd olunmalıdır.',
            'mobile.regex'      => 'Mobil nömrə 12 rəqəmdən ibarət olmalıdır. Misal: 994501234567',
            'email.required'    => 'Email qeyd olunmalıdır.',
            'email.email'       => 'Düzgün bir email formatı daxil edilməlidir. Misal: user@example.com',
            'email.unique'      => 'Bu email artıq qeydiyyatdan keçib. Zəhmət olmasa, başqa bir email istifadə edin.',
            'password.required' => 'Şifrə qeyd olunmalıdır.',
            'password.min'      => 'Şifrə minimum 6 simvoldan ibarət olmalıdır.',
            'password.max'      => 'Şifrə maksimum 20 simvoldan ibarət olmalıdır.',
        ];
    }
}
