<?php

namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Product\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function items(Customer $customer): array
    {
        return DB::table('customer_cart_items as cart')
            ->join('product_variants as variants', 'variants.id', '=', 'cart.product_variant_id')
            ->where('cart.customer_id', $customer->id)->orderBy('cart.id')
            ->get(['variants.product_id', 'cart.product_variant_id as variant_id', 'cart.quantity'])
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'variant_id' => (int) $item->variant_id,
                'quantity' => (int) $item->quantity,
            ])->all();
    }

    public function merge(Customer $customer, array $items, string $token): array
    {
        return DB::transaction(function () use ($customer, $items, $token) {
            $this->lock($customer);
            if (!DB::table('customer_cart_imports')->where('customer_id', $customer->id)->where('token', $token)->exists())
            {
                $quantities = collect($items)->groupBy('variant_id')
                    ->map(fn ($rows) => min(99, $rows->sum('quantity')));
                $valid = ProductVariant::whereIn('id', $quantities->keys())->where('active', 1)
                    ->whereHas('product', fn ($q) => $q->where('active', 1))->pluck('id');
                foreach ($valid as $id)
                {
                    $this->addQuantity($customer, (int) $id, (int) $quantities[$id]);
                }
                DB::table('customer_cart_imports')->insert(['customer_id' => $customer->id, 'token' => $token, 'created_at' => now()]);
            }

            return $this->items($customer);
        });
    }

    public function change(Customer $customer, int $variantId, string $action, int $quantity = 1): array
    {
        return DB::transaction(function () use ($customer, $variantId, $action, $quantity) {
            $this->lock($customer);
            $row = $this->row($customer, $variantId);
            if ($action === 'remove')
            {
                $row->delete();
            }
            elseif ($action === 'decrease')
            {
                $current = $row->value('quantity');
                if ($current !== null) $row->update(['quantity' => max(1, (int) $current - $quantity), 'updated_at' => now()]);
            }
            else
            {
                $valid = ProductVariant::whereKey($variantId)->where('active', 1)
                    ->whereHas('product', fn ($q) => $q->where('active', 1))->exists();
                if (!$valid)
                {
                    throw ValidationException::withMessages(['variant_id' => __('validation_your_cart_contains_an_unavailable_product')]);
                }
                $this->addQuantity($customer, $variantId, $quantity);
            }

            return $this->items($customer);
        });
    }

    /** Sifariş yaradılan transaction daxilində müştərinin səbətini tam boşaldır. */
    public function clear(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $this->lock($customer);
            DB::table('customer_cart_items')->where('customer_id', $customer->id)->delete();
        });
    }

    private function lock(Customer $customer): void
    {
        DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();
    }

    private function row(Customer $customer, int $variantId)
    {
        return DB::table('customer_cart_items')->where('customer_id', $customer->id)->where('product_variant_id', $variantId);
    }

    private function addQuantity(Customer $customer, int $variantId, int $quantity): void
    {
        $row = $this->row($customer, $variantId);
        $current = $row->value('quantity');
        $values = ['quantity' => min(99, (int) $current + $quantity), 'updated_at' => now()];
        if ($current === null)
        {
            DB::table('customer_cart_items')->insert($values + [
                'customer_id' => $customer->id, 'product_variant_id' => $variantId, 'created_at' => now(),
            ]);
        }
        else $row->update($values);
    }
}
