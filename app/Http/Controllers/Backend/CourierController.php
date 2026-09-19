<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\CourierCreateRequest;
use App\Models\Couriers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CourierController extends Controller
{
    public function index()
    {
        $couriers = Couriers::all();

        return view('backend.couriers.list',[
            'couriers'    => $couriers
        ]);
    }

    public function create(CourierCreateRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['password'] = Hash::make($request->password);

        Couriers::create($validatedData);
        return redirect()->back()->with('success', 'Kuryer uğurla yaradıldı!');
    }

    public function edit($id)
    {
        $courier = Couriers::findOrFail($id);
        return view('backend.couriers.edit',[
            'courier' => $courier
        ]);
    }

    public function update(Request $request)
    {
        $courier = Couriers::findOrFail($request->id);

        $courier->name = $request->name;
        $courier->surname = $request->surname;
        $courier->email = $request->email;
        $courier->mobile = $request->mobile;
        $courier->active = $request->active;
        if($request->password){
            $courier->password = Hash::make($request->password);
        }
        $courier->save();


        return redirect(route('courier.list'))->with('success', 'Kuryer məlumatları yeniləndi!');
    }
}
