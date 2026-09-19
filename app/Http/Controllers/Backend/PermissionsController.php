<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderbydesc('id')->paginate(20);
        return view('backend.permissions.list',[
            'permissions' => $permissions,
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3',
            'description'   => 'required|string|min:10'
        ],[
            'name.*'    => 'Səhifə adı minimum 3 hərfdən ibarət olmalıdır !',
            'description.*'    => 'Məlumat minimum 10 hərfdən ibarət olmalıdır !'
        ]);

        Permission::create($validated);

        return redirect()->back()->with('success', 'Səhifə əlavə edildi !');
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);
        return view('backend.permissions.edit',[
            'permission' => $permission
        ]);
    }

    public function update(Request $request,$id)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3',
            'description'   => 'required|string|min:10'
        ],[
            'name.*'    => 'Səhifə adı minimum 3 hərfdən ibarət olmalıdır !',
            'description.*'    => 'Məlumat minimum 10 hərfdən ibarət olmalıdır !'
        ]);

        Permission::findOrFail($id)->update($validated);

        return redirect()->route('admin.permission.list')->with('success', 'Düzəliş olundu !');
    }
}
