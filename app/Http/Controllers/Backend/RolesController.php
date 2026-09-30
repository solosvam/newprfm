<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Support\PermissionGroups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return view('backend.roles.list',[
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

        Role::create($validated + ['guard_name' => PermissionsController::GUARD]);

        session()->flash('success', 'Rol əlavə edildi !');
        return redirect()->back();
    }

    public function edit($id)
    {
        $role = Role::where('id',$id)->first();

        if(!$role){
            return redirect(route('admin.role.list'))->with('error', 'Rol tapılmadı !');
        }

        return view('backend.roles.edit',[
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
        return redirect()->route('admin.role.list')->with('success', 'Düzəliş olundu !');

    }

    public function permissions($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::where('guard_name', $role->guard_name)->get();

        return view('backend.roles.permissions', [
            'role' => $role,
            'groups' => PermissionGroups::group($permissions),
            'granted' => $role->permissions()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'total' => $permissions->count(),
            'guardMismatch' => $role->guard_name !== PermissionsController::GUARD,
        ]);
    }

    /**
     * Rolun icazələri: bir və ya bir neçə (bölmə üzrə "hamısı") icazəni verir/götürür.
     * Öz rolundan "role.list"-i götürmək olmaz — yoxsa bu səhifəyə girişi itirər.
     */
    public function togglePermissions(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'permission_ids' => ['required', 'array', 'min:1', 'max:200'],
            'permission_ids.*' => ['integer'],
            'checked' => ['required', 'boolean'],
        ]);
        $permissions = Permission::whereIn('id', $data['permission_ids'])->where('guard_name', $role->guard_name)->get();
        if ($permissions->count() !== count(array_unique($data['permission_ids']))) {
            return response()->json(['message' => 'İcazə tapılmadı və ya rolun guard-ı ilə uyğun deyil.'], 422);
        }
        $me = auth('admin')->user();
        if (!$data['checked'] && $me?->hasRole($role->name, $role->guard_name) && $permissions->contains('name', 'role.list')) {
            return response()->json(['message' => 'Öz rolunuzdan "role.list" icazəsini götürə bilməzsiniz — bu səhifəyə girişi itirərdiniz.'], 422);
        }

        DB::transaction(function () use ($role, $permissions, $data) {
            $ids = $permissions->pluck('id')->all();
            $data['checked'] ? $role->permissions()->syncWithoutDetaching($ids) : $role->permissions()->detach($ids);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => $data['checked'] ? 'İcazə verildi.' : 'İcazə götürüldü.',
            'granted' => $role->permissions()->pluck('id')->map(fn ($id) => (int) $id)->values(),
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
