<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Exceptions\PromoCodeException;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function __construct(private PromoCodeService $promo) {}

    public function apply(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $subtotal = $this->promo->subtotalFor($data['items']);

            $result = $this->promo->resolve(
                $data['code'],
                $subtotal
            );
        } catch (PromoCodeException $e) {
            session()->forget('promo_code');

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        session([
            'promo_code' => $result['promo']->code,
        ]);

        return response()->json([
            'code' => $result['promo']->code,
            'discount' => $result['discount'],
        ]);
    }

    public function remove()
    {
        session()->forget('promo_code');

        return response()->noContent();
    }
}
