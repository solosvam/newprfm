<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CreditPeriod;
use App\Models\CreditTerms;
use App\Models\CreditTermItem;
use App\Models\CreditApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function periods()
    {
        return view('backend.credit.periods', [
            'periods' => CreditPeriod::orderBy('sort_order')->orderBy('month')->get(),
        ]);
    }

    public function updatePeriods(Request $request)
    {
        $data = $request->validate([
            'periods' => ['nullable', 'array'],
            'periods.*.id' => ['nullable', 'integer'],
            'periods.*.month' => ['required', 'integer', 'min:1', 'max:120'],
            'periods.*.interest_rate' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'periods.*.active' => ['nullable', 'boolean'],
        ]);

        $rows = collect($data['periods'] ?? []);
        if ($rows->pluck('month')->duplicates()->isNotEmpty()) {
            return back()->withInput()->withErrors(['periods' => 'Eyni ay yalnız bir dəfə əlavə edilə bilər.']);
        }

        DB::transaction(function () use ($rows) {
            $keepIds = [];

            foreach ($rows->values() as $index => $row) {
                $period = !empty($row['id'])
                    ? CreditPeriod::find($row['id'])
                    : new CreditPeriod();

                if (!$period) {
                    continue;
                }

                $period->fill([
                    'month' => $row['month'],
                    'interest_rate' => $row['interest_rate'],
                    'active' => $row['active'] ?? false,
                    'sort_order' => $index + 1,
                ])->save();

                $keepIds[] = $period->id;
            }

            CreditPeriod::when($keepIds, fn ($query) => $query->whereNotIn('id', $keepIds))
                ->when(empty($keepIds), fn ($query) => $query)
                ->delete();
        });

        return back()->with('success', 'Kredit faizləri yeniləndi!');
    }

    public function applications()
    {
        return view('backend.credit.applications', [
            'applications' => CreditApplication::query()
                ->join('customers', 'customers.id', '=', 'credit_applications.customer_id')
                ->join('product_variants', 'product_variants.id', '=', 'credit_applications.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->select('credit_applications.*', 'customers.name as customer_name', 'customers.surname as customer_surname',
                    'customers.mobile as customer_mobile', 'products.name as product_name')
                ->orderByDesc('credit_applications.id')
                ->paginate(30),
        ]);
    }

    public function terms()
    {
        return view('backend.credit.terms', [
            'items' => CreditTermItem::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function updateTerms(Request $request)
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer', 'exists:credit_term_items,id'],
            'items.*.content_az' => ['required', 'string'],
            'items.*.content_en' => ['nullable', 'string'],
            'items.*.content_ru' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $ids = [];
            foreach (array_values($data['items'] ?? []) as $index => $row) {
                $item = !empty($row['id']) ? CreditTermItem::findOrFail($row['id']) : new CreditTermItem();
                unset($row['id']);
                $item->fill($row + ['sort_order' => $index + 1])->save();
                $ids[] = $item->id;
            }
            CreditTermItem::when($ids, fn ($q) => $q->whereNotIn('id', $ids))->delete();
        });

        return back()->with('success', 'Şərtlər və qaydalar yeniləndi!');
    }
}
