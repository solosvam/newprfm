<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\ProductionReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** MÜVƏQQƏTİ: canlıya çıxmazdan əvvəl test məlumatının silinməsi (ProductionReset). Yalnız "Super admin", menyuda yoxdur. */
class ResetController extends Controller
{
    public function index(ProductionReset $reset): View
    {
        $this->authorizeReset();

        return view('backend.reset', ['sections' => ProductionReset::SECTIONS, 'counts' => $reset->counts()]);
    }

    public function run(Request $request, ProductionReset $reset): RedirectResponse
    {
        $this->authorizeReset();
        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['in:'.implode(',', array_keys(ProductionReset::SECTIONS))],
            'confirm' => ['required', 'in:SIFIRLA'],
        ], ['sections.required' => 'Ən azı bir bölmə seçin.', 'confirm.in' => 'Təsdiq üçün böyük hərflərlə SIFIRLA yazın.', 'confirm.required' => 'Təsdiq üçün SIFIRLA yazın.']);

        $before = $reset->counts();
        $reset->run($data['sections']);
        Log::warning('Production reset', ['user_id' => auth('admin')->id(), 'sections' => $data['sections'], 'counts' => array_intersect_key($before, array_flip($data['sections']))]);

        return back()->with('success', 'Silindi: '.implode('; ', array_map(fn ($key) => ProductionReset::SECTIONS[$key], $data['sections'])).'.');
    }

    private function authorizeReset(): void
    {
        abort_unless(auth('admin')->user()?->hasRole('Super admin'), 403, 'Bu səhifə yalnız Super admin üçündür.');
    }
}
