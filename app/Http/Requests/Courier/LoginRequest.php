<?php

namespace App\Http\Requests\Courier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'email'     => 'required|email|exists:couriers,email',
            'password'  => 'required|min:6'
        ];
    }

    public function messages()
    {
        return [
            'email.required'    => 'Email qeyd olunmalıdır.',
            'email.exists'      => 'Email səhfdir.',
            'email.email'       => 'Düzgün bir email formatı daxil edilməlidir. Misal: user@example.com',
            'password.required' => 'Şifrə qeyd olunmalıdır və minimum 6 simvoldan ibarət olmalıdır.',
            'password.min'      => 'Şifrə minimum 6 simvoldan ibarət olmalıdır.'
        ];
    }

    public function checkAccount($admin)
    {
        if (!$admin->getAttribute('active')){
            throw ValidationException::withMessages([
                'email' => trans('Hesab aktiv deyil'),
            ]);
        }
    }
}
