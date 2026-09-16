<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginpage()
    {
        return view('admin.pages.authentication.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password','active');

        if (Auth::guard('admin')->attempt($credentials)) {
            $admin = Auth::guard('admin')->user();
            $request->checkAccount($admin);
            return redirect()->intended(route('main'));
        }else{
            throw ValidationException::withMessages([
                'password' => trans('Şifrə səhfdir'),
            ]);
        }
    }

    public function logout()
    {
        Auth::guard('admin')->logout();
        return redirect(route('loginpage'));
    }

}
