<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AjaxController extends Controller
{
    public function setRolePermission(Request $request) {
        $role = Role::findOrFail($request->role_id);
        $permission = Permission::findOrFail($request->perm_id);

        $checked = filter_var($request->checked, FILTER_VALIDATE_BOOLEAN);

        if($checked){
            $role->givePermissionTo($permission->name);
        }else{
            $role->revokePermissionTo($permission->name);
        }
    }
}
