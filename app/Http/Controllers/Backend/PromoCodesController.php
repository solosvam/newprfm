<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodesController extends Controller
{
    public function index(Request $request)
    {
        $promos = PromoCode::query()->orderByDesc('id')->get();

        return view('backend.promo-codes.index', compact('promos'));
    }

    public function create()
    {
        return view('backend.promo-codes.form', ['promo' => new PromoCode()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active');
        $data['used_count'] = 0;
        PromoCode::create($data);

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo kod yaradıldı.');
    }

    public function edit(PromoCode $promo)
    {
        return view('backend.promo-codes.form', compact('promo'));
    }

    public function update(Request $request, PromoCode $promo)
    {
        $data = $this->validated($request, $promo);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active');
        $promo->update($data);

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo kod yeniləndi.');
    }

    public function history(PromoCode $promo)
    {
        $orders = Order::with('customer', 'paymentMethod')
            ->where('promo_code_id', $promo->id)
            ->latest()->paginate(25);

        return view('backend.promo-codes.history', compact('promo', 'orders'));
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promo_codes', 'code')->ignore($promo?->id)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:99999999', Rule::when($request->input('type') === 'percent', ['lte:100'])],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'gt:0'],
            'usage_limit' => ['nullable', 'integer', 'min:' . max(1, (int) ($promo?->used_count ?? 0))],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
