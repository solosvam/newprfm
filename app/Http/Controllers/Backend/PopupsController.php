<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Admin → Sayt → Popup-lar: siyahı (statistika ilə), yarat / redaktə / sil.
 * Saytda göstərilmə — Popup::forVisitor + frontend/js/popup.js.
 */
class PopupsController extends Controller
{
    private const IMAGE_DIR = 'frontend/uploads/popups';

    public function index(): View
    {
        $popups = Popup::query()
            ->withSum('stats as shown', 'shown')->withSum('stats as clicked', 'clicked')
            ->withSum('stats as closed', 'closed')->withSum('stats as dismissed', 'dismissed')
            ->orderBy('sort_order')->orderByDesc('id')
            ->get();

        return view('backend.popups.index', compact('popups'));
    }

    public function create(): View
    {
        return view('backend.popups.form', ['popup' => new Popup([
            'position_desktop' => 'center', 'position_mobile' => 'bar', 'audience' => 'all', 'pages' => 'all',
            'delay_seconds' => 3, 'frequency' => 'once', 'active' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $popup = new Popup(['created_by' => auth('admin')->id(), 'sort_order' => (int) Popup::max('sort_order') + 1]);
        $this->save($request, $popup);

        return redirect()->route('admin.popups.index')->with('success', 'Popup yaradıldı.');
    }

    public function edit(Popup $popup): View
    {
        $daily = DB::table('popup_stats')->where('popup_id', $popup->id)
            ->where('day', '>=', today()->subDays(13)->toDateString())->orderBy('day')->get()->keyBy(fn ($r) => substr($r->day, 0, 10));

        return view('backend.popups.form', compact('popup', 'daily'));
    }

    public function update(Request $request, Popup $popup): RedirectResponse
    {
        $this->save($request, $popup);

        return redirect()->route('admin.popups.edit', $popup)->with('success', 'Popup yeniləndi.');
    }

    public function destroy(Popup $popup): RedirectResponse
    {
        $image = $popup->image;
        DB::transaction(function () use ($popup) {
            // FK cascade-dən asılı olmadan (sqlite-da söndürülü ola bilər)
            DB::table('popup_stats')->where('popup_id', $popup->id)->delete();
            DB::table('popup_dismissals')->where('popup_id', $popup->id)->delete();
            $popup->delete();
        });
        if ($image) {
            File::delete(public_path(self::IMAGE_DIR.'/'.basename($image)));
        }

        return redirect()->route('admin.popups.index')->with('success', 'Popup silindi.');
    }

    private function save(Request $request, Popup $popup): void
    {
        $positions = array_keys(Popup::POSITIONS);
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'link_url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                // tam link (https://...) və ya saytdaxili yol (/brands)
                if ($value && !str_starts_with($value, '/') && !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    $fail('Link https:// və ya / ilə başlamalıdır.');
                }
            }],
            'image' => ['nullable', 'image', 'max:10240'],
            'remove_image' => ['nullable', 'boolean'],
            'position_desktop' => ['required', Rule::in($positions)],
            'position_mobile' => ['required', Rule::in($positions)],
            'audience' => ['required', Rule::in(array_keys(Popup::AUDIENCES))],
            'pages_all' => ['nullable', 'boolean'],
            'pages' => ['nullable', 'array'],
            'pages.*' => [Rule::in(array_keys(Popup::PAGES))],
            'frequency' => ['required', Rule::in(array_keys(Popup::FREQUENCIES))],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'active' => ['nullable', 'boolean'],
        ];
        foreach (Popup::LOCALES as $locale) {
            $rules['title_'.$locale] = ['nullable', 'string', 'max:150'];
            $rules['body_'.$locale] = ['nullable', 'string', 'max:1000'];
            $rules['button_'.$locale] = ['nullable', 'string', 'max:60'];
        }
        $data = $request->validate($rules, [
            'ends_at.after' => 'Bitmə tarixi başlama tarixindən sonra olmalıdır.',
            'image.image' => 'Yüklənən fayl şəkil olmalıdır.',
            'image.max' => 'Şəklin həcmi maksimum 10 MB ola bilər.',
        ], ['name' => 'ad', 'link_url' => 'link', 'delay_seconds' => 'gecikmə']);

        $hasImage = $request->hasFile('image') || ($popup->image && !$request->boolean('remove_image'));
        if (!$hasImage && empty($data['title_az'])) {
            throw ValidationException::withMessages(['title_az' => 'Azərbaycan dilində başlıq və ya şəkil olmalıdır.']);
        }
        if (!empty($data['button_az']) && empty($data['link_url'])) {
            throw ValidationException::withMessages(['link_url' => 'Düymə üçün link yazın (və ya düymə mətnini silin).']);
        }

        $pages = array_values(array_intersect(array_keys(Popup::PAGES), $data['pages'] ?? []));
        if (!$request->boolean('pages_all') && !$pages) {
            throw ValidationException::withMessages(['pages' => 'Popup-un görünəcəyi səhifəni seçin (və ya "Bütün səhifələr").']);
        }

        $popup->fill(collect($data)->except(['image', 'remove_image', 'active', 'pages', 'pages_all'])->all());
        // hamısı seçilibsə də "all" deyil — sonra yeni səhifə növü əlavə olunsa, avtomatik oraya düşməsin
        $popup->pages = $request->boolean('pages_all') ? 'all' : implode(',', $pages);
        $popup->active = $request->boolean('active');

        $oldImage = $popup->image;
        if ($request->hasFile('image')) {
            $popup->image = $this->storeImage($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $popup->image = null;
        }
        $popup->save();

        if ($oldImage && $oldImage !== $popup->image) {
            File::delete(public_path(self::IMAGE_DIR.'/'.basename($oldImage)));
        }
    }

    private function storeImage($file): string
    {
        $name = 'popup-'.Str::lower(Str::random(10)).'.webp';
        File::ensureDirectoryExists(public_path(self::IMAGE_DIR));
        ImageManager::usingDriver(Driver::class)->decode($file)
            ->scaleDown(width: 1200)
            ->save(public_path(self::IMAGE_DIR.'/'.$name), quality: 82);

        return $name;
    }
}
