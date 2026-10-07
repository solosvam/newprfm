<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Marketinq → Məlumat səhifələri: sabit səhifələrin (Page::PAGES) mətni 3 dildə (Quill redaktoru).
 */
class PagesController extends Controller
{
    public function index(): View
    {
        $pages = Page::all()->keyBy('key');

        return view('backend.pages-content.index', compact('pages'));
    }

    public function edit(Page $page): View
    {
        return view('backend.pages-content.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $rules = ['title_az' => ['required', 'string', 'max:150']];
        foreach (Page::LOCALES as $locale) {
            $rules['title_'.$locale] ??= ['nullable', 'string', 'max:150'];
            $rules['body_'.$locale] = ['nullable', 'string', 'max:100000'];
            $rules['meta_description_'.$locale] = ['nullable', 'string', 'max:300'];
        }
        $data = $request->validate($rules, [], ['title_az' => 'başlıq (AZ)']);

        foreach (Page::LOCALES as $locale) {
            $body = Page::clean($data['body_'.$locale] ?? '');
            // Quill boş redaktorda "<p><br></p>" saxlayır
            $data['body_'.$locale] = trim(strip_tags($body)) === '' ? null : $body;
        }
        $page->fill($data + ['updated_by' => auth('admin')->id()])->save();

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Səhifə yeniləndi.');
    }
}
