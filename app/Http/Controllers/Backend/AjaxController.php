<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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

    public function searchCustomerCrm(Request $request)
    {
        $q = trim($request->q);

        if (!$q) return response()->json([]);

        $customers = collect();

        // 0 ile baslayan 10 rəqəm — mobil nömrə
        if (preg_match('/^0\d{9}$/', $q)) {
            $mobile = "994".substr($q, 1);

            $customers = Customer::where('mobile', $mobile)
                ->limit(5)
                ->get();
        }

        // Nöqtə ilə başlayır — FIN kod
        elseif (str_starts_with($q, '.')) {
            $fin = ltrim($q, '.');
            $customers = Customer::where('fin', 'like', "%{$fin}%")
                ->limit(5)
                ->get();
        }

        // Ad soyad — boşluq var
        elseif (str_contains($q, ' ')) {
            [$name, $surname] = explode(' ', $q, 2);

            $customers = Customer::where('name', 'like', "%{$name}%")
                ->where('surname', 'like', "%{$surname}%")->limit(5)->get();
        }

        $results = [];

        foreach ($customers as $c) {
            $results[] = [
                'id'           => $c->id,
                'fullname'     => $c->fullname,
            ];
        }

        return response()->json($results);
    }
}
