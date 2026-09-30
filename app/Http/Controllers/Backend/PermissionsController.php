<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Support\PermissionGroups;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    /** Admin icazələri guard "admin"-dədir (auth.defaults.guard "web"-dir — açıq yazılmalıdır). */
    public const GUARD = 'admin';

    public function index()
    {
        // Əvvəl paginate(20) idi, amma səhifələmə linki yox idi — 20-dən sonrakılar görünmürdü
        // Bölmə sırası (PermissionGroups::GROUPS), sonra ada görə — # sütunu bu sıranı göstərir
        $permissions = PermissionGroups::group(Permission::with('roles:id,name')->get())->flatMap(fn ($g) => $g['items']);

        return view('backend.permissions.list', [
            'permissions' => $permissions,
            'groupLabel' => fn (string $name) => PermissionGroups::label(PermissionGroups::keyFor($name)),
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/',
                Rule::unique('permissions', 'name')->where('guard_name', self::GUARD)->ignore($ignoreId)],
            'description' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'İcazənin kodunu yazın.',
            'name.regex' => 'Kod yalnız kiçik latın hərfi, rəqəm və nöqtədən ibarət olmalıdır (məs. site.banners).',
            'name.unique' => 'Bu kodla icazə artıq var.',
            'name.*' => 'Kod 3–100 simvol olmalıdır.',
            'description.*' => 'Açıqlama 3–255 simvol olmalıdır.',
        ];
    }

    public function add(Request $request)
    {
        $validated = $request->validateWithBag('create', $this->rules(), $this->messages());
        Permission::create($validated + ['guard_name' => self::GUARD]);

        return redirect()->back()->with('success', 'İcazə əlavə edildi.');
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);

        return view('backend.permissions.edit', ['permission' => $permission]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);
        $validated = $request->validate($this->rules($permission->id), $this->messages());
        $permission->update($validated);

        return redirect()->route('admin.permission.list')->with('success', 'Düzəliş olundu.');
    }
}
