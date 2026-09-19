<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::all();

        return view('backend.faq.list',[
            'faqs'    => $faqs
        ]);
    }

    public function create(Request $request)
    {
        Faq::create([
            'title_az'  => $request->title_az,
            'title_en'  => $request->title_en,
            'title_ru'  => $request->title_ru,
            'content_az'  => $request->content_az,
            'content_en'  => $request->content_en,
            'content_ru'  => $request->content_ru,
        ]);

        return redirect()->back()->with('success', 'Sual - Cavab uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $faq = Faq::findOrFail($id);
        return view('backend.faq.edit',[
            'faq' => $faq
        ]);
    }

    public function update(Request $request)
    {
        $faq = Faq::findOrFail($request->id);

        $faq->title_az = $request->title_az;
        $faq->title_en = $request->title_en;
        $faq->title_ru = $request->title_ru;
        $faq->content_az = $request->content_az;
        $faq->content_en = $request->content_en;
        $faq->content_ru = $request->content_ru;
        $faq->save();


        return redirect(route('admin.faq.list'))->with('success', 'Sual - cavab yeniləndi!');
    }
}
