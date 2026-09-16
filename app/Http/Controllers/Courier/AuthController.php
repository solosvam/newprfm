<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginpage()
    {
        return view('courier.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password','active');

        if (Auth::guard('courier')->attempt($credentials)) {
            $courier = Auth::guard('courier')->user();
            $request->checkAccount($courier);
            return redirect()->intended(route('main'));
        }else{
            throw ValidationException::withMessages([
                'password' => trans('Şifrə səhfdir'),
            ]);
        }
    }

    public function logout()
    {
        Auth::guard('courier')->logout();
        return redirect(route('loginpage'));
    }
}
