<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return view('admin.roles.list',[
            'roles' => $roles
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:4',
            'description'   => 'required|string|min:10'
        ],[
            'name.*'    => 'Rol adı minimum 4 hərfdən ibarət olmalıdır !',
            'description.*'    => 'Rol minimum 10 hərfdən ibarət olmalıdır !'
        ]);

        Role::create($validated);

        session()->flash('success', 'Rol əlavə edildi !');
        return redirect()->back();
    }

    public function edit($id)
    {
        $role = Role::where('id',$id)->first();

        if(!$role){
            return redirect(route('admin.role.list'))->with('error', 'Rol tapılmadı !');
        }

        return view('admin.roles.edit',[
            'role' => $role
        ]);
    }

    public function update(Request $request,$id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:4',
            'description'   => 'required|string|min:10'
        ],[
            'name.*'    => 'Rol adı minimum 4 hərfdən ibarət olmalıdır !',
            'description.*'    => 'Rol məlumatı minimum 10 hərfdən ibarət olmalıdır !'
        ]);

        $role->update($validated);
        return redirect()->route('role.list')->with('success', 'Düzəliş olundu !');

    }

    public function permissions($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::all();

        return view('admin.roles.permissions',[
            'role'          => $role,
            'permissions'   => $permissions
        ]);
    }

    public function updateRole(Request $request)
    {
        $role = Role::findOrFail($request->json('role_id'));

        if($request->json('checked')){
            $role->givePermissionTo($request->json('perm_id'));
        }else{
            $role->revokePermissionTo($request->json('perm_id'));
        }
    }
}
