<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('backend.pages.authentication.login');
    }

    public function loginSubmit(LoginRequest $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'active' => 1,
        ];

        if (!Auth::guard('admin')->attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'password' => 'Email və ya şifrə yanlışdır.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('admin')->user();

        $request->checkAccount($user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout()
    {
        Auth::guard('admin')->logout();
        return redirect(route('admin.login.form'));
    }

}
