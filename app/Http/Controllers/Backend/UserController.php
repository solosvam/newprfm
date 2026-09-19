<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCreateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $user = User::all();

        return view('backend.user.list',[
            'users'    => $user
        ]);
    }

    public function create(AdminCreateRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['password'] = Hash::make($request->password);

        User::create($validatedData);
        return redirect()->back()->with('success', 'Admin uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();
        return view('backend.user.edit',[
            'user' => $user,
            'roles' => $roles
        ]);
    }

    public function update(Request $request)
    {
        $admin = User::findOrFail($request->id);

        $admin->name = $request->name;
        $admin->surname = $request->surname;
        $admin->email = $request->email;
        $admin->mobile = $request->mobile;
        $admin->active = $request->active;
        if($request->password){
            $admin->password = Hash::make($request->password);
        }
        $admin->save();

        $admin->assignRole($request->role_name);

        return redirect(route('admin.user.list'))->with('success', 'Admin məlumatları yeniləndi!');
    }
}
