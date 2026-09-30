<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCreditProfile;
use App\Models\Product\Product;
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
        $kind = null;   // phone | fin | name — tapılmayanda JS-ə lazımdır (nömrədə "Yeni müştəri yarat")
        $mobile = null;

        // Mobil nömrə: 0103227575, 994103227575, +994 10 322 75 75, 103227575 (994-dən sonra 0 olmur)
        $digits = preg_replace('/[\s\-()+]/', '', $q);
        if (ctype_digit($digits) && preg_match('/^(?:994|0)?([1-9]\d{8})$/', $digits, $m)) {
            $kind = 'phone';
            $mobile = '994'.$m[1];

            $customers = Customer::where('mobile', $mobile)
                ->limit(5)
                ->get();
        }

        // Nöqtə ilə başlayır — FIN kod
        elseif (str_starts_with($q, '.')) {
            $kind = 'fin';
            $fin = ltrim($q, '.');

            $customers = Customer::whereHas('creditProfile', function ($query) use ($fin) {
                $query->where('fin', 'like', "%{$fin}%");
            })
                ->limit(5)
                ->get();
        }

        // Ad soyad — boşluq var
        elseif (str_contains($q, ' ')) {
            $kind = 'name';
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

        return response()->json(['kind' => $kind, 'mobile' => $mobile, 'results' => $results]);
    }

    /**
     * CRM → "Yeni sifariş" modalında məhsul axtarışı (köhnə saytdakı məntiq).
     *  - Boşluq varsa: ilk söz brend adında, qalanı məhsul adında axtarılır ("dol de" → Dolce... + Devotion...).
     *  - Boşluq yoxdursa: söz həm brend, həm məhsul adında axtarılır.
     * Yalnız aktiv məhsullar və aktiv variantlar, ən yenidən köhnəyə, 10 nəticə.
     */
    public function searchProductCrm(Request $request)
    {
        $q = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->query('q'))));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // LIKE-da % və _ istifadəçinin yazdığı simvol kimi qalsın
        $like = fn (string $value) => '%' . addcslashes($value, '%_\\') . '%';

        $products = Product::query()
            ->where('products.active', 1)
            ->whereHas('variants', fn ($v) => $v->where('active', 1))
            ->when(
                str_contains($q, ' '),
                function ($query) use ($q, $like) {
                    [$brand, $name] = explode(' ', $q, 2);
                    $query->whereHas('brand', fn ($b) => $b->where('name', 'like', $like($brand)))
                        ->where('products.name', 'like', $like($name));
                },
                function ($query) use ($q, $like) {
                    $query->where(function ($w) use ($q, $like) {
                        $w->where('products.name', 'like', $like($q))
                            ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like($q)));
                    });
                }
            )
            ->with([
                'brand:id,name',
                'type:id,name_az',
                'genders:id,name_az',
                'images' => fn ($i) => $i->limit(1),
                'variants' => fn ($v) => $v->where('active', 1)->with('size:id,name_az')->orderBy('price'),
            ])
            ->orderByDesc('products.id')
            ->limit(10)
            ->get();

        return response()->json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'brand' => $p->brand?->name,
            'type' => $p->type?->name_az,
            'gender' => $p->genders->first()?->name_az,
            'image' => ($img = $p->images->first()) ? asset('frontend/uploads/products/' . $img->image) : null,
            'variants' => $p->variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->size?->name_az ?? '—',
                'price' => (float) $v->price,
            ])->values(),
        ])->values());
    }
}
