<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::where('active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy(function($brand) {
                return strtoupper(substr($brand->name, 0, 1)); // İlk hərfə görə qrupla
            });

        return view('frontend.brands', compact('brands'));
    }
}
