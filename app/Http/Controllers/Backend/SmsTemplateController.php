<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        return view('backend.sms_templates.index', ['templates'=>SmsTemplate::orderBy('id')->get()]);
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        $data=$request->validate(['template'=>['required','string','max:1000'],'active'=>['nullable','boolean']]);
        $smsTemplate->update(['template'=>$data['template'],'active'=>$request->boolean('active')]);
        return back()->with('success','SMS şablonu yeniləndi !');
    }
}
