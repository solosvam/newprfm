<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CreditPeriod;
use App\Models\CreditTerms;
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

    public function terms()
    {
        return view('backend.credit.terms', [
            'terms' => CreditTerms::first() ?? new CreditTerms(),
        ]);
    }

    public function updateTerms(Request $request)
    {
        $data = $request->validate([
            'content_az' => ['nullable', 'string'],
            'content_en' => ['nullable', 'string'],
            'content_ru' => ['nullable', 'string'],
        ]);

        $terms = CreditTerms::first() ?? new CreditTerms();
        $terms->fill($data)->save();

        return back()->with('success', 'Şərtlər və qaydalar yeniləndi!');
    }
}
