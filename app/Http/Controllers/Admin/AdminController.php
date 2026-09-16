<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCreateRequest;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::all();

        return view('admin.admins.list',[
            'admins'    => $admins
        ]);
    }

    public function create(AdminCreateRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['password'] = Hash::make($request->password);

        Admin::create($validatedData);
        return redirect()->back()->with('success', 'Admin uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $admin = Admin::findOrFail($id);
        $roles = Role::all();
        return view('admin.admins.edit',[
            'admin' => $admin,
            'roles' => $roles
        ]);
    }

    public function update(Request $request)
    {
        $admin = Admin::findOrFail($request->id);

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

        return redirect(route('admin.list'))->with('success', 'Admin məlumatları yeniləndi!');
    }
}
