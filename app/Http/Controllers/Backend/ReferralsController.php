<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerReferral;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Satış → Dəvətlər (referal):
 *  - view=invites (standart): hər dəvət — kim kimi dəvət edib, status, bonusu yaradan sifariş, yazılan məbləğlər;
 *  - view=referrers: dəvət edənlər üzrə — neçə nəfər dəvət edib, neçəsi üçün bonus alıb, cəmi bonus.
 * status=registered|rewarded — süzgəc; q — ad / telefon (dəvət edən və ya dəvət olunan).
 */
class ReferralsController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view') === 'referrers' ? 'referrers' : 'invites';
        $status = in_array($request->query('status'), [CustomerReferral::STATUS_REGISTERED, CustomerReferral::STATUS_REWARDED], true)
            ? $request->query('status') : null;
        $q = trim((string) $request->query('q'));

        $totals = CustomerReferral::query()
            ->selectRaw('COUNT(*) as invites, SUM(status = ?) as rewarded, SUM(status = ?) as pending,
                COALESCE(SUM(referrer_amount), 0) as referrer_paid, COALESCE(SUM(invitee_amount), 0) as invitee_paid',
                [CustomerReferral::STATUS_REWARDED, CustomerReferral::STATUS_REGISTERED])
            ->first();

        // hər söz ad, soyad və ya telefonda olmalıdır ("aysel 050" → adı Aysel, nömrəsində 050)
        $matches = function (Builder $customer) use ($q) {
            foreach (array_filter(explode(' ', $q), 'strlen') as $word) {
                $digits = preg_replace('/\D+/', '', $word);
                $customer->where(fn (Builder $w) => $w->where('name', 'like', "%$word%")->orWhere('surname', 'like', "%$word%")
                    ->when(strlen($digits) >= 3, fn (Builder $w) => $w->orWhere('mobile', 'like', "%$digits%")));
            }
        };

        if ($view === 'referrers') {
            $rows = DB::table('customer_referrals')
                ->join('customers', 'customers.id', '=', 'customer_referrals.referrer_id')
                ->when($q !== '', fn ($query) => $query->whereIn('customers.id', Customer::query()->select('id')->where($matches)->toBase()))
                ->groupBy('customers.id', 'customers.name', 'customers.surname', 'customers.mobile')
                ->selectRaw('customers.id, customers.name, customers.surname, customers.mobile, COUNT(*) as invites,
                    SUM(customer_referrals.status = ?) as rewarded, COALESCE(SUM(customer_referrals.referrer_amount), 0) as earned,
                    MAX(customer_referrals.created_at) as last_invite', [CustomerReferral::STATUS_REWARDED])
                ->orderByDesc('invites')->orderByDesc('rewarded')
                ->paginate(30)->withQueryString();

            return view('backend.referrals.index', compact('view', 'status', 'q', 'totals', 'rows'));
        }

        $rows = CustomerReferral::query()
            ->with(['referrer:id,name,surname,mobile', 'invitee:id,name,surname,mobile', 'order:id,order_no,customer_id,referral_discount'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->whereHas('referrer', $matches)->orWhereHas('invitee', $matches)))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('backend.referrals.index', compact('view', 'status', 'q', 'totals', 'rows'));
    }
}
